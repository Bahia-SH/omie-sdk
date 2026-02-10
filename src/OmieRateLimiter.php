<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Exceptions\OmieRateLimitExceededException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class OmieRateLimiter
{
    /**
     * @param  \Illuminate\Contracts\Cache\Repository  $cache  Deve ser um store que suporte locks (ex.: redis, database).
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected CacheRepository $cache,
        protected array $config = []
    ) {
    }

    public function checkOrWait(string $appKey, string $method, ?string $ip = null): void
    {
        $rateConfig = $this->config['rate_limit'] ?? [];

        $perIpLimit = (int) ($rateConfig['per_ip_per_minute'] ?? 960);
        $perAppMethodLimit = (int) ($rateConfig['per_app_method_per_minute'] ?? 240);
        $concurrentPerAppMethod = (int) ($rateConfig['concurrent_per_app_method'] ?? 4);

        $nowMinute = (int) (time() / 60);

        if ($ip !== null && $perIpLimit > 0) {
            $this->waitForCountLimit(
                sprintf('omie:rate:ip:%s:%d', $ip, $nowMinute),
                $perIpLimit,
                'omie:lock:ip'
            );
        }

        if ($perAppMethodLimit > 0) {
            $this->waitForCountLimit(
                sprintf('omie:rate:app:%s:%s:%d', $appKey, $method, $nowMinute),
                $perAppMethodLimit,
                sprintf('omie:lock:app:%s:%s', $appKey, $method)
            );
        }

        if ($concurrentPerAppMethod > 0) {
            $this->waitForConcurrentLimit(
                sprintf('omie:concurrent:%s:%s', $appKey, $method),
                $concurrentPerAppMethod,
                sprintf('omie:lock:concurrent:%s:%s', $appKey, $method)
            );
        }
    }

    protected function waitForCountLimit(string $countKey, int $limit, string $lockKey): void
    {
        $maxWait = 70;
        $waited = 0;

        while ($waited < $maxWait) {
            /** @var object|null $lock */
            $lock = null;
            if (\is_object($this->cache) && method_exists($this->cache, 'lock')) {
                /** @var callable $lockMethod */
                $lockMethod = [$this->cache, 'lock'];
                $lock = $lockMethod($lockKey, 5);
            }
            if ($lock === null || ! $lock->get()) {
                usleep(100_000);
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
                if ($lock !== null) {
                    $lock->release();
                }
            }

            sleep(1);
            $waited++;
        }

        throw new OmieRateLimitExceededException('Limite de requisições da API Omie excedido.');
    }

    protected function waitForConcurrentLimit(string $counterKey, int $limit, string $lockKey): void
    {
        $maxWait = 120;
        $waited = 0;

        while ($waited < $maxWait) {
            /** @var object|null $lock */
            $lock = null;
            if (\is_object($this->cache) && method_exists($this->cache, 'lock')) {
                /** @var callable $lockMethod */
                $lockMethod = [$this->cache, 'lock'];
                $lock = $lockMethod($lockKey, 5);
            }
            if ($lock === null || ! $lock->get()) {
                usleep(200_000);
                $waited++;
                continue;
            }

            try {
                $current = (int) $this->cache->get($counterKey, 0);
                if ($current < $limit) {
                    $this->cache->put($counterKey, $current + 1, 300);

                    return;
                }
            } finally {
                if ($lock !== null) {
                    $lock->release();
                }
            }

            sleep(1);
            $waited++;
        }

        throw new OmieRateLimitExceededException('Limite de requisições simultâneas da API Omie excedido.');
    }
}

