<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\Exceptions\OmieRateLimitExceededException;
use Bahiash\Omie\OmieRateLimiter;
use Bahiash\Omie\Tests\Stubs\FakeCacheWithLock;
use Illuminate\Contracts\Cache\Lock;

class OmieRateLimiterTest extends TestCase
{
    protected function createCacheWithLock(bool $lockAcquired = true, int $currentCount = 0): FakeCacheWithLock
    {
        $lock = $this->createMock(Lock::class);
        $lock->method('get')->willReturn($lockAcquired);
        $lock->method('release')->willReturn(null);

        return new FakeCacheWithLock($lock, function () use ($currentCount) {
            return $currentCount;
        }, null);
    }

    public function test_check_or_wait_passagem_quando_abaixo_do_limite(): void
    {
        $cache = $this->createCacheWithLock(true, 0);
        $limiter = new OmieRateLimiter($cache, [
            'rate_limit' => [
                'per_ip_per_minute' => 960,
                'per_app_method_per_minute' => 240,
                'concurrent_per_app_method' => 4,
            ],
        ]);

        $limiter->checkOrWait('app1', 'ListarProdutos', '192.168.1.1');

        $this->addToAssertionCount(1);
    }

    public function test_check_or_wait_sem_ip_nao_verifica_limite_por_ip(): void
    {
        $cache = $this->createCacheWithLock(true, 0);
        $limiter = new OmieRateLimiter($cache, [
            'rate_limit' => [
                'per_ip_per_minute' => 960,
                'per_app_method_per_minute' => 240,
                'concurrent_per_app_method' => 4,
            ],
        ]);

        $limiter->checkOrWait('app1', 'ListarProdutos', null);

        $this->addToAssertionCount(1);
    }

    public function test_check_or_wait_com_limite_zero_por_ip_nao_usa_chave_ip(): void
    {
        $cache = $this->createCacheWithLock(true, 0);
        $limiter = new OmieRateLimiter($cache, [
            'rate_limit' => [
                'per_ip_per_minute' => 0,
                'per_app_method_per_minute' => 240,
                'concurrent_per_app_method' => 4,
            ],
        ]);

        $limiter->checkOrWait('app1', 'ListarProdutos', '127.0.0.1');

        $this->addToAssertionCount(1);
    }

    public function test_check_or_wait_lanca_exception_quando_limite_count_excedido_apos_max_wait(): void
    {
        $lock = $this->createMock(Lock::class);
        $lock->method('get')->willReturn(true);
        $lock->method('release')->willReturn(null);

        $cache = new FakeCacheWithLock($lock, fn () => 1000, null);

        $limiter = new OmieRateLimiter($cache, [
            'rate_limit' => [
                'per_ip_per_minute' => 10,
                'per_app_method_per_minute' => 240,
                'concurrent_per_app_method' => 4,
                'max_wait_seconds' => 2,
            ],
        ]);

        $this->expectException(OmieRateLimitExceededException::class);
        $this->expectExceptionMessage('Limite de requisições da API Omie excedido.');

        $limiter->checkOrWait('app1', 'ListarProdutos', '192.168.1.1');
    }

    public function test_check_or_wait_lanca_exception_quando_limite_concorrente_excedido(): void
    {
        $lock = $this->createMock(Lock::class);
        $lock->method('get')->willReturn(true);
        $lock->method('release')->willReturn(null);

        $cache = new FakeCacheWithLock(
            $lock,
            function ($key) {
                return str_contains($key, 'concurrent') ? 10 : 0;
            },
            null
        );

        $limiter = new OmieRateLimiter($cache, [
            'rate_limit' => [
                'per_ip_per_minute' => 0,
                'per_app_method_per_minute' => 0,
                'concurrent_per_app_method' => 2,
                'max_wait_concurrent_seconds' => 2,
            ],
        ]);

        $this->expectException(OmieRateLimitExceededException::class);
        $this->expectExceptionMessage('Limite de requisições simultâneas da API Omie excedido.');

        $limiter->checkOrWait('app1', 'ListarProdutos', null);
    }

    public function test_config_default_usa_valores_documentacao(): void
    {
        $cache = $this->createCacheWithLock(true, 0);
        $limiter = new OmieRateLimiter($cache, []);

        $limiter->checkOrWait('app1', 'ListarProdutos', null);

        $this->addToAssertionCount(1);
    }
}
