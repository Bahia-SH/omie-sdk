<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\Logging\OmieApiLogger;
use Bahiash\Omie\OmieRateLimiter;
use Bahiash\Omie\Services\ProdutosService;

class OmieServiceProviderTest extends TestCase
{
    public function test_config_omie_esta_merged(): void
    {
        $this->assertSame('https://app.omie.com.br/api/v1/', config('omie.base_url'));
        $this->assertIsArray(config('omie.rate_limit'));
        $this->assertArrayHasKey('per_ip_per_minute', config('omie.rate_limit'));
        $this->assertArrayHasKey('queue', config('omie'));
        $this->assertArrayHasKey('logging', config('omie'));
    }

    public function test_rate_limiter_registrado_como_singleton(): void
    {
        $a = $this->app->make(OmieRateLimiter::class);
        $b = $this->app->make(OmieRateLimiter::class);

        $this->assertInstanceOf(OmieRateLimiter::class, $a);
        $this->assertSame($a, $b);
    }

    public function test_omie_api_logger_registrado_como_singleton(): void
    {
        $a = $this->app->make(OmieApiLogger::class);
        $b = $this->app->make(OmieApiLogger::class);

        $this->assertInstanceOf(OmieApiLogger::class, $a);
        $this->assertSame($a, $b);
    }

    public function test_produtos_service_registrado_como_singleton(): void
    {
        $a = $this->app->make(ProdutosService::class);
        $b = $this->app->make(ProdutosService::class);

        $this->assertInstanceOf(ProdutosService::class, $a);
        $this->assertSame($a, $b);
    }

    public function test_migrations_existem_em_package(): void
    {
        $migrationsPath = __DIR__ . '/../database/migrations';
        $this->assertDirectoryExists($migrationsPath);
        $files = glob($migrationsPath . '/*.php');
        $this->assertNotEmpty($files);
    }
}
