<?php

namespace Bahiash\Omie\Jobs;

use Bahiash\Omie\Events\OmieCallFailed;
use Bahiash\Omie\Events\OmieCallSucceeded;
use Bahiash\Omie\Exceptions\OmieApiException;
use Bahiash\Omie\Logging\OmieApiLogger;
use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\OmieClient;
use Bahiash\Omie\OmieRateLimiter;
use GuzzleHttp\ClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DispatchOmieCallJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public string $correlationId;
    public string $appKey;
    public string $appSecret;
    public string $servicePath;
    public string $method;

    /** @var array<int|string, mixed> */
    public array $params;

    public ?string $eventClass;

    /** @var array<string, mixed> */
    public array $eventParams;

    public ?string $tenantId;
    public ?string $ipOrigem;

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>|null  $eventParams
     */
    public function __construct(
        string $appKey,
        string $appSecret,
        string $servicePath,
        string $method,
        array $params = [],
        ?string $eventClass = null,
        ?array $eventParams = null,
        ?string $correlationId = null,
        ?string $tenantId = null,
        ?string $ipOrigem = null
    ) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->servicePath = $servicePath;
        $this->method = $method;
        $this->params = $params;
        $this->eventClass = $eventClass;
        $this->eventParams = $eventParams ?? [];
        $this->correlationId = $correlationId ?? (string) Str::uuid();
        $this->tenantId = $tenantId;
        $this->ipOrigem = $ipOrigem;

        $queueConfig = (array) Config::get('omie.queue', []);

        if (! empty($queueConfig['connection'])) {
            $this->onConnection($queueConfig['connection']);
        }
        if (! empty($queueConfig['queue'])) {
            $this->onQueue($queueConfig['queue']);
        }

        $this->tries = (int) ($queueConfig['tries'] ?? 5);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $backoff = Config::get('omie.queue.backoff', [10, 30, 60, 120, 300]);

        return is_array($backoff) ? array_map('intval', $backoff) : [10, 30, 60, 120, 300];
    }

    public function handle(
        OmieRateLimiter $rateLimiter,
        OmieApiLogger $logger,
        ClientInterface $http
    ): void {
        $baseUrl = (string) Config::get('omie.base_url', 'https://app.omie.com.br/api/v1/');
        $httpConfig = (array) Config::get('omie.http', []);
        $loggingEnabled = (bool) Config::get('omie.logging.enabled', true);
        $legacySingleEvent = (bool) Config::get('omie.events.legacy_single_event', true);

        $start = microtime(true);

        $log = null;

        if ($loggingEnabled) {
            $log = $logger->startLog([
                'correlation_id' => $this->correlationId,
                'tenant_id' => $this->tenantId,
                'attempt' => $this->attempts(),
                'app_key' => $this->appKey,
                'service_path' => $this->servicePath,
                'method' => $this->method,
                'request_body' => [
                    'app_key' => $this->appKey,
                    'service_path' => $this->servicePath,
                    'method' => $this->method,
                    'params' => $this->params,
                ],
                'ip_origem' => $this->ipOrigem,
                'event_class' => $this->eventClass,
                'event_params' => $this->eventParams,
                'status' => OmieApiLog::STATUS_RUNNING,
            ]);
        }

        $rateAcquired = false;

        try {
            $rateLimiter->acquire($this->appKey, $this->method, $this->ipOrigem);
            $rateAcquired = true;

            $client = new OmieClient($this->appKey, $this->appSecret, $baseUrl, $http, $httpConfig);

            $response = $client->call($this->servicePath, $this->method, $this->params);

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $omieStatusCode = Arr::get($response, 'codigo_status');
            $omieStatusMessage = Arr::get($response, 'descricao_status');

            if ($log !== null) {
                $logger->finishLogSuccess(
                    $log,
                    $response,
                    200,
                    [
                        'duration_ms' => $durationMs,
                        'omie_status_code' => $omieStatusCode,
                        'omie_status_message' => $omieStatusMessage,
                        'attempt' => $this->attempts(),
                    ]
                );

                $this->dispatchEvents($log, true, $legacySingleEvent);
            }
        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if ($log !== null) {
                $logger->finishLogError(
                    $log,
                    $e,
                    null,
                    [
                        'duration_ms' => $durationMs,
                        'attempt' => $this->attempts(),
                    ]
                );

                $this->dispatchEvents($log, false, $legacySingleEvent);
            }

            Log::error('Erro ao chamar API Omie', [
                'correlation_id' => $this->correlationId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'app_key' => $this->appKey,
                'service_path' => $this->servicePath,
                'method' => $this->method,
                'attempt' => $this->attempts(),
            ]);

            if ($e instanceof OmieApiException && ! $e->isRetryable()) {
                $this->fail($e);

                return;
            }

            throw $e;
        } finally {
            if ($rateAcquired) {
                try {
                    $rateLimiter->releaseConcurrent($this->appKey, $this->method);
                } catch (Throwable $releaseEx) {
                    Log::warning('Falha ao liberar slot concorrente Omie', [
                        'correlation_id' => $this->correlationId,
                        'message' => $releaseEx->getMessage(),
                    ]);
                }
            }
        }
    }

    public function failed(Throwable $e): void
    {
        if (! (bool) Config::get('omie.logging.enabled', true)) {
            return;
        }

        $log = OmieApiLog::findByCorrelationId($this->correlationId);
        if ($log === null) {
            return;
        }

        // Já finalizado pelo handle() — handle já gravou status e disparou evento.
        if ($log->isFinished()) {
            return;
        }

        $log->status = OmieApiLog::STATUS_FAILED;
        $log->error_class ??= $e::class;
        $log->error_message ??= $e->getMessage();
        $log->finished_at = now();
        $log->save();

        $this->dispatchEvents($log, false, (bool) Config::get('omie.events.legacy_single_event', true));
    }

    protected function dispatchEvents(OmieApiLog $log, bool $success, bool $legacy): void
    {
        Event::dispatch($success
            ? new OmieCallSucceeded($log, $this->eventParams)
            : new OmieCallFailed($log, $this->eventParams)
        );

        if ($legacy && $this->eventClass !== null && $this->eventClass !== '' && class_exists($this->eventClass)) {
            Event::dispatch(new $this->eventClass($log, $this->eventParams));
        }
    }
}
