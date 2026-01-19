<?php

namespace Bahiash\Omie;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Http\Message\ResponseInterface;

class OmieClient
{
    protected string $baseUrl;

    protected string $appKey;

    protected string $appSecret;

    protected ClientInterface $http;

    protected ?CacheRepository $cache;

    protected array $config;

    public function __construct(array $config, ClientInterface $http, ?CacheRepository $cache = null)
    {
        $this->config = $config;
        $this->baseUrl = rtrim($config['base_url'], '/') . '/';
        $this->appKey = (string) ($config['app_key'] ?? '');
        $this->appSecret = (string) ($config['app_secret'] ?? '');
        $this->http = $http;
        $this->cache = $cache;
    }

    /**
     * Executa uma chamada à API OMIE.
     *
     * @param  string  $servicePath  Ex: "geral/ajustesestoque"
     * @param  string  $method  Ex: "IncluirAjusteEstoque"
     * @param  array  $param  Parâmetros (será enviado como param[0] se for um único objeto)
     * @return array Resposta JSON decodificada
     *
     * @throws \RuntimeException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function call(string $servicePath, string $method, array $param = []): array
    {
        $param = $this->normalizeParam($param);

        if ($this->shouldUseCache($method, $param)) {
            $cached = $this->getFromCache($servicePath, $method, $param);
            if ($cached !== null) {
                return $cached;
            }
        }

        $restrictedLock = $this->acquireRestrictedLockIfApplicable($method);

        $semaphoreKey = null;
        if ($this->shouldUseSemaphore($method)) {
            $semaphoreKey = $this->acquireSemaphore($method);
        }

        try {
            $this->waitForPerIpLimit();
            $this->waitForPerMethodLimit($method);

            $response = $this->doRequest($servicePath, $method, $param);

            if ($this->shouldUseCache($method, $param)) {
                $this->putInCache($servicePath, $method, $param, $response);
            }

            return $response;
        } finally {
            if ($semaphoreKey !== null) {
                $this->releaseSemaphore($method, $semaphoreKey);
            }
            if ($restrictedLock !== null && method_exists($restrictedLock, 'release')) {
                $restrictedLock->release();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $param
     * @return array<int, mixed>
     */
    protected function normalizeParam(array $param): array
    {
        if (array_is_list($param) || $param === []) {
            return $param;
        }

        return [$param];
    }

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

        $response = $this->http->request('POST', $url, [
            'json' => $body,
        ]);

        return $this->decodeResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida da API OMIE: ' . json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : ['raw' => $contents];
    }

    // --- Rate Limit ---

    /**
     * Adquire o lock para métodos restritos (1 requisição por vez).
     * O lock deve ser liberado no finally de call().
     *
     * @return object|null Lock com método release() ou null
     */
    protected function acquireRestrictedLockIfApplicable(string $method): ?object
    {
        $rl = $this->config['rate_limit'] ?? [];
        $restricted = $rl['restricted_methods'] ?? [];

        if (! in_array($method, $restricted, true) || $this->cache === null) {
            return null;
        }

        $lockKey = 'omie:restricted:' . $method;
        $lock = $this->cache->lock($lockKey, 60);
        $lock->block(90);

        return $lock;
    }

    protected function waitForPerIpLimit(): void
    {
        $limit = (int) ($this->config['rate_limit']['per_ip_per_minute'] ?? 960);
        $key = 'omie:count:ip:' . (int) (time() / 60);

        $this->waitForCountLimit($key, $limit, 'omie:lock:ip');
    }

    protected function waitForPerMethodLimit(string $method): void
    {
        $limit = (int) ($this->config['rate_limit']['per_method_per_minute'] ?? 240);
        $key = 'omie:count:method:' . $this->appKey . ':' . $method . ':' . (int) (time() / 60);

        $this->waitForCountLimit($key, $limit, 'omie:lock:method:' . $method);
    }

    protected function waitForCountLimit(string $countKey, int $limit, string $lockKey): void
    {
        if ($this->cache === null || $limit <= 0) {
            return;
        }

        $maxWait = 70;
        $waited = 0;

        while ($waited < $maxWait) {
            $lock = $this->cache->lock($lockKey, 5);
            if (! $lock->get()) {
                usleep(100000);
                $waited++;
                continue;
            }

            try {
                $current = (int) $this->cache->get($countKey, 0);
                if ($current < $limit) {
                    $this->cache->put($countKey, $current + 1, 120);

                    return;
                }
            } finally {
                $lock->release();
            }

            sleep(1);
            $waited++;
        }

        throw new \RuntimeException('OmieClient: timeout aguardando rate limit (IP ou método).');
    }

    protected function shouldUseSemaphore(string $method): bool
    {
        return ($this->config['rate_limit']['concurrent_per_method'] ?? 4) > 0;
    }

    protected function acquireSemaphore(string $method): string
    {
        if ($this->cache === null) {
            return '';
        }

        $max = (int) ($this->config['rate_limit']['concurrent_per_method'] ?? 4);
        $key = 'omie:concurrent:' . $this->appKey . ':' . $method;
        $lockKey = 'omie:lock:concurrent:' . $method;
        $maxWait = 120;
        $waited = 0;

        while ($waited < $maxWait) {
            $lock = $this->cache->lock($lockKey, 5);
            if (! $lock->get()) {
                usleep(200000);
                $waited += 1;

                continue;
            }

            try {
                $current = (int) $this->cache->get($key, 0);
                if ($current < $max) {
                    $this->cache->put($key, $current + 1, 300);
                    $slot = $key . ':' . uniqid('', true);
                    $this->cache->put($slot, time(), 300);

                    return $slot;
                }
            } finally {
                $lock->release();
            }

            sleep(1);
            $waited++;
        }

        throw new \RuntimeException('OmieClient: timeout aguardando semáforo de requisições simultâneas.');
    }

    protected function releaseSemaphore(string $method, string $semaphoreKey): void
    {
        if ($this->cache === null || $semaphoreKey === '') {
            return;
        }

        $key = 'omie:concurrent:' . $this->appKey . ':' . $method;
        $lockKey = 'omie:lock:concurrent:' . $method;

        $lock = $this->cache->lock($lockKey, 5);
        if ($lock->get()) {
            $current = (int) $this->cache->get($key, 1);
            $this->cache->put($key, max(0, $current - 1), 300);
            $this->cache->forget($semaphoreKey);
            $lock->release();
        }
    }

    // --- Cache (evitar consultas redundantes em < 60s) ---

    protected function shouldUseCache(string $method, array $param): bool
    {
        $cacheConfig = $this->config['cache'] ?? [];
        if (empty($cacheConfig['enabled'])) {
            return false;
        }

        return in_array($method, $cacheConfig['listar_methods'] ?? [], true);
    }

    protected function getCacheKey(string $servicePath, string $method, array $param): string
    {
        ksort($param);

        return 'omie:cache:' . md5($servicePath . '|' . $method . '|' . json_encode($param));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getFromCache(string $servicePath, string $method, array $param): ?array
    {
        if ($this->cache === null) {
            return null;
        }

        $key = $this->getCacheKey($servicePath, $method, $param);
        $data = $this->cache->get($key);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function putInCache(string $servicePath, string $method, array $param, array $response): void
    {
        if ($this->cache === null) {
            return;
        }

        $ttl = (int) ($this->config['cache']['ttl_seconds'] ?? 60);
        $key = $this->getCacheKey($servicePath, $method, $param);
        $this->cache->put($key, $response, $ttl);
    }
}
