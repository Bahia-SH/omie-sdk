<?php

namespace Bahiash\Omie\Jobs;

use Bahiash\Omie\Logging\OmieApiLogger;
use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\OmieClient;
use Bahiash\Omie\OmieRateLimiter;
use GuzzleHttp\ClientInterface;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchOmieCallJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public string $appKey;

    public string $appSecret;

    public string $servicePath;

    public string $method;

    /**
     * @var array<int|string, mixed>
     */
    public array $params;

    public ?string $eventClass;

    /**
     * @var array<string, mixed>
     */
    public ?array $eventParams;

    /**
     * @var array<string, mixed>
     */

     protected ?array $config;

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $eventParams
     */
    public function __construct(
        string $appKey,
        string $appSecret,
        string $servicePath,
        string $method,
        array $params = [],
        ?string $eventClass = null,
        ?array $eventParams = null
    ) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->servicePath = $servicePath;
        $this->method = $method;
        $this->params = $params;
        $this->eventClass = $eventClass;
        $this->eventParams = $eventParams ?? [];

        $this->config = Config::get('omie', []);

        if (! empty($this->config['queue']['connection'])) {
            $this->onConnection($this->config['queue']['connection']);
        }
        if (! empty($this->config['queue']['queue'])) {
            $this->onQueue($this->config['queue']['queue']);
        }
    }

    public function handle(
        OmieRateLimiter $rateLimiter,
        OmieApiLogger $logger,
        ClientInterface $http
    ): void {
        
        $baseUrl = (string) Arr::get($this->config, 'base_url', 'https://app.omie.com.br/api/v1/');
        $ip = null;

        $start = microtime(true);

        $requestBody = [
            'app_key' => $this->appKey,
            'service_path' => $this->servicePath,
            'method' => $this->method,
            'params' => $this->params,
        ];

        $maskedRequest = $this->maskSensitive($requestBody);

        /** @var OmieApiLog $log */
        $log = $logger->startLog([
            'app_key' => $this->appKey,
            'service_path' => $this->servicePath,
            'method' => $this->method,
            'request_body' => $maskedRequest,
            'ip_origem' => $ip,
            'event_class' => $this->eventClass,
            'event_params' => $this->eventParams,
        ]);

        try {
            $rateLimiter->checkOrWait($this->appKey, $this->method, $ip);

            $client = new OmieClient($this->appKey, $this->appSecret, $baseUrl, $http);

            $response = $client->call($this->servicePath, $this->method, $this->params);

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $omieStatusCode = Arr::get($response, 'codigo_status');
            $omieStatusMessage = Arr::get($response, 'descricao_status');

            $logger->finishLogSuccess(
                $log,
                $response,
                200,
                [
                    'duration_ms' => $durationMs,
                    'omie_status_code' => $omieStatusCode,
                    'omie_status_message' => $omieStatusMessage,
                ]
            );

            $this->dispatchEvent($log);
        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $logger->finishLogError(
                $log,
                $e,
                null,
                [
                    'duration_ms' => $durationMs,
                ]
            );

            $this->dispatchEvent($log);

            Log::error('Erro ao chamar API Omie', [
                'exception' => $e,
                'app_key' => $this->appKey,
                'service_path' => $this->servicePath,
                'method' => $this->method,
                'event_params' => $this->eventParams,
            ]);

            throw $e;
        }
    }

    protected function dispatchEvent(OmieApiLog $log): void
    {
        if ($this->eventClass === null || $this->eventClass === '') {
            return;
        }

        if (! class_exists($this->eventClass)) {
            return;
        }

        Event::dispatch(new $this->eventClass($log, $this->eventParams));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitive(array $data): array
    {
        $loggingConfig = Config::get('omie.logging', []);
        $masked = $data;
        $fields = (array) ($loggingConfig['masked_fields'] ?? []);

        foreach ($fields as $field) {
            if (array_key_exists($field, $masked)) {
                $masked[$field] = '***';
            }
        }

        return $masked;
    }
}

