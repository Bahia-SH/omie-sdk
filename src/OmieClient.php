<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Exceptions\OmieApiException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class OmieClient
{
    protected string $baseUrl;
    protected string $appKey;
    protected string $appSecret;
    protected ClientInterface $http;

    /** @var array<string, mixed> */
    protected array $httpConfig;

    /**
     * @param  array<string, mixed>  $httpConfig  ['timeout','connect_timeout','retry'=>['enabled','max_attempts','base_delay_ms']]
     */
    public function __construct(
        string $appKey,
        string $appSecret,
        string $baseUrl,
        ClientInterface $http,
        array $httpConfig = []
    ) {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->http = $http;
        $this->httpConfig = $httpConfig;
    }

    /**
     * @param  array<int|string, mixed>  $param
     * @return array<string, mixed>
     *
     * @throws OmieApiException
     */
    public function call(string $servicePath, string $method, array $param = []): array
    {
        $param = $this->normalizeParam($param);

        return $this->doRequestWithRetry($servicePath, $method, $param);
    }

    /**
     * @param  array<string, mixed>  $param
     * @return array<int, mixed>|array<string, mixed>
     */
    protected function normalizeParam(array $param): array
    {
        if (array_is_list($param) || $param === []) {
            return $param;
        }

        return [$param];
    }

    /**
     * @param  array<int|string, mixed>  $param
     * @return array<string, mixed>
     */
    protected function doRequestWithRetry(string $servicePath, string $method, array $param): array
    {
        $retry = (array) ($this->httpConfig['retry'] ?? []);
        $enabled = (bool) ($retry['enabled'] ?? false);
        $maxAttempts = max(1, (int) ($retry['max_attempts'] ?? 1));
        $baseDelayMs = max(0, (int) ($retry['base_delay_ms'] ?? 0));

        if (! $enabled) {
            $maxAttempts = 1;
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                return $this->doRequest($servicePath, $method, $param);
            } catch (OmieApiException $e) {
                $lastException = $e;
                if (! $e->isRetryable() || $attempt >= $maxAttempts) {
                    throw $e;
                }
                $this->sleepBackoff($attempt, $baseDelayMs);
            }
        }

        throw $lastException ?? new OmieApiException('Falha desconhecida ao chamar API Omie');
    }

    protected function sleepBackoff(int $attempt, int $baseDelayMs): void
    {
        if ($baseDelayMs <= 0) {
            return;
        }
        $delay = $baseDelayMs * (2 ** ($attempt - 1));
        $jitter = random_int(0, (int) ($delay * 0.2));
        usleep(($delay + $jitter) * 1000);
    }

    /**
     * @param  array<int|string, mixed>  $param
     * @return array<string, mixed>
     */
    protected function doRequest(string $servicePath, string $method, array $param): array
    {
        $url = $this->baseUrl . ltrim($servicePath, '/');
        if (! str_ends_with($url, '/')) {
            $url .= '/';
        }

        $body = [
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret,
            'call' => $method,
            'param' => $param,
        ];

        $options = [
            'json' => $body,
            'http_errors' => true,
        ];
        if (isset($this->httpConfig['timeout'])) {
            $options['timeout'] = (int) $this->httpConfig['timeout'];
        }
        if (isset($this->httpConfig['connect_timeout'])) {
            $options['connect_timeout'] = (int) $this->httpConfig['connect_timeout'];
        }

        try {
            $response = $this->http->request('POST', $url, $options);
        } catch (ConnectException $e) {
            throw new OmieApiException(
                'Falha de conexão com API Omie: ' . $e->getMessage(),
                null,
                null,
                null,
                null,
                true,
                $e
            );
        } catch (BadResponseException $e) {
            $resp = $e->getResponse();
            $status = $resp?->getStatusCode();
            $payload = $resp ? $this->safeDecode((string) $resp->getBody()) : null;

            throw OmieApiException::fromOmiePayload(
                is_array($payload) ? $payload : ['raw' => (string) $resp?->getBody()],
                $status
            );
        } catch (GuzzleException $e) {
            throw new OmieApiException(
                'Erro Guzzle: ' . $e->getMessage(),
                null,
                null,
                null,
                null,
                true,
                $e
            );
        }

        $decoded = $this->decodeResponse($response);

        if (isset($decoded['faultcode']) || isset($decoded['faultstring'])) {
            throw OmieApiException::fromOmiePayload($decoded, $response->getStatusCode());
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new OmieApiException(
                'Resposta inválida da API Omie: ' . json_last_error_msg(),
                $response->getStatusCode()
            );
        }

        return is_array($decoded) ? $decoded : ['raw' => $contents];
    }

    protected function safeDecode(string $contents): mixed
    {
        $decoded = json_decode($contents, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
