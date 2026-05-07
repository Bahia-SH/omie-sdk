<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Exceptions\OmieRateLimitExceededException;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class OmieRateLimiter
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected CacheRepository $cache,
        protected array $config = []
    ) {
    }

    public function acquire(string $appKey, string $method, ?string $ip = null): void
    {
        $rate = (array) ($this->config['rate_limit'] ?? []);

        $perIp = (int) ($rate['per_ip_per_minute'] ?? 960);
        $perApp = (int) ($rate['per_app_method_per_minute'] ?? 240);
        $concurrent = (int) ($rate['concurrent_per_app_method'] ?? 4);
        $strategy = (string) ($rate['strategy'] ?? 'fixed');

        $minute = (int) (time() / 60);

        if ($ip !== null && $perIp > 0) {
            $this->waitForCountLimit(
                $this->countKey('ip', $ip, $minute, $strategy),
                $perIp,
                'omie:lock:ip:' . $ip,
                $strategy
            );
        }

        if ($perApp > 0) {
            $this->waitForCountLimit(
                $this->countKey('app', $appKey . ':' . $method, $minute, $strategy),
                $perApp,
                sprintf('omie:lock:app:%s:%s', $appKey, $method),
                $strategy
            );
        }

        if ($concurrent > 0) {
            $this->acquireConcurrent($appKey, $method, $concurrent);
        }
    }

    public function releaseConcurrent(string $appKey, string $method): void
    {
        if ((int) ($this->config['rate_limit']['concurrent_per_app_method'] ?? 0) <= 0) {
            return;
        }

        $counterKey = sprintf('omie:concurrent:%s:%s', $appKey, $method);
        $lockKey = sprintf('omie:lock:concurrent:%s:%s', $appKey, $method);

        $this->withLock($lockKey, 5, function () use ($counterKey) {
            $current = (int) $this->cache->get($counterKey, 0);
            $next = max(0, $current - 1);
            if ($next === 0) {
                $this->cache->forget($counterKey);
            } else {
                $this->cache->put($counterKey, $next, 300);
            }
        });
    }

    /** @deprecated Use acquire() + releaseConcurrent(). */
    public function checkOrWait(string $appKey, string $method, ?string $ip = null): void
    {
        $this->acquire($appKey, $method, $ip);
    }

    protected function countKey(string $kind, string $id, int $minute, string $strategy): string
    {
        return $strategy === 'sliding'
            ? sprintf('omie:rate:%s:%s:sliding', $kind, $id)
            : sprintf('omie:rate:%s:%s:%d', $kind, $id, $minute);
    }

    protected function waitForCountLimit(string $countKey, int $limit, string $lockKey, string $strategy): void
    {
        $maxWait = (int) ($this->config['rate_limit']['max_wait_seconds'] ?? 70);

        $acquired = $this->lockedWait(
            $lockKey,
            $maxWait,
            100_000,
            function () use ($countKey, $limit, $strategy) {
                if ($strategy === 'sliding') {
                    return $this->slidingTryIncrement($countKey, $limit);
                }

                $current = (int) $this->cache->get($countKey, 0);
                if ($current < $limit) {
                    $this->cache->put($countKey, $current + 1, 120);
                    return true;
                }

                return false;
            }
        );

        if (! $acquired) {
            throw new OmieRateLimitExceededException('Limite de requisições da API Omie excedido.');
        }
    }

    protected function acquireConcurrent(string $appKey, string $method, int $limit): void
    {
        $counterKey = sprintf('omie:concurrent:%s:%s', $appKey, $method);
        $lockKey = sprintf('omie:lock:concurrent:%s:%s', $appKey, $method);
        $maxWait = (int) ($this->config['rate_limit']['max_wait_concurrent_seconds'] ?? 120);

        $acquired = $this->lockedWait($lockKey, $maxWait, 200_000, function () use ($counterKey, $limit) {
            $current = (int) $this->cache->get($counterKey, 0);
            if ($current < $limit) {
                $this->cache->put($counterKey, $current + 1, 300);
                return true;
            }
            return false;
        });

        if (! $acquired) {
            throw new OmieRateLimitExceededException('Limite de requisições simultâneas da API Omie excedido.');
        }
    }

    /**
     * Polling com lock até acquire callback retornar true ou estourar maxWait (em segundos).
     */
    protected function lockedWait(string $lockKey, int $maxWaitSeconds, int $sleepUs, \Closure $tryAcquire): bool
    {
        $waited = 0;
        while ($waited < $maxWaitSeconds) {
            $taken = $this->withLock($lockKey, 5, $tryAcquire);
            if ($taken === true) {
                return true;
            }
            if ($taken === null) {
                usleep($sleepUs);
                $waited++;
                continue;
            }
            sleep(1);
            $waited++;
        }

        return false;
    }

    /**
     * Executa $fn com lock; retorna o valor de $fn, ou null se lock indisponível.
     */
    protected function withLock(string $key, int $ttl, \Closure $fn): mixed
    {
        if (! $this->cache instanceof LockProvider) {
            return $fn();
        }

        $lock = $this->cache->lock($key, $ttl);
        if (! $lock->get()) {
            return null;
        }
        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }

    protected function slidingTryIncrement(string $key, int $limit): bool
    {
        $now = microtime(true);
        $windowStart = $now - 60.0;

        /** @var array<int, float> $timestamps */
        $timestamps = (array) $this->cache->get($key, []);
        $timestamps = array_values(array_filter($timestamps, fn ($t) => (float) $t >= $windowStart));

        if (count($timestamps) >= $limit) {
            $this->cache->put($key, $timestamps, 120);
            return false;
        }

        $timestamps[] = $now;
        $this->cache->put($key, $timestamps, 120);

        return true;
    }
}
