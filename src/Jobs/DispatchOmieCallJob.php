<?php

namespace Bahiash\Omie\Jobs;

use Bahiash\Omie\Logging\OmieApiLogger;
use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\OmieClient;
use Bahiash\Omie\OmieRateLimiter;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchOmieCallJob implements ShouldQueue
{
    /**
     * Conexão de fila a ser utilizada pelo job.
     */
    public ?string $connection = null;

    /**
     * Nome da fila (queue) a ser utilizada pelo job.
     */
    public ?string $queue = null;

    public string $appKey;

    public string $appSecret;

    public string $servicePath;

    public string $method;

    /**
     * @var array<int|string, mixed>
     */
    public array $params;

    public ?string $correlationId;

    public function __construct(
        string $appKey,
        string $appSecret,
        string $servicePath,
        string $method,
        array $params = [],
        ?string $correlationId = null
    ) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->servicePath = $servicePath;
        $this->method = $method;
        $this->params = $params;
        $this->correlationId = $correlationId;

        $queueConfig = Config::get('omie.queue', []);
        if (! empty($queueConfig['connection'])) {
            $this->connection = $queueConfig['connection'];
        }
        if (! empty($queueConfig['queue'])) {
            $this->queue = $queueConfig['queue'];
        }
    }

    public function handle(
        OmieRateLimiter $rateLimiter,
        OmieApiLogger $logger,
        ClientInterface $http
    ): void {
        $config = Config::get('omie');
        $baseUrl = (string) Arr::get($config, 'base_url', 'https://app.omie.com.br/api/v1/');
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
            'correlation_id' => $this->correlationId,
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

            Log::error('Erro ao chamar API Omie', [
                'exception' => $e,
                'app_key' => $this->appKey,
                'service_path' => $this->servicePath,
                'method' => $this->method,
                'correlation_id' => $this->correlationId,
            ]);

            throw $e;
        }
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

