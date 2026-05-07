<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Logging\OmieApiLogger;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class OmieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/omie.php', 'omie');

        if (! $this->app->bound(ClientInterface::class)) {
            $this->app->singleton(ClientInterface::class, fn () => new GuzzleClient([
                'base_uri' => config('omie.base_url', 'https://app.omie.com.br/api/v1/'),
                'timeout' => (int) config('omie.http.timeout', 30),
                'connect_timeout' => (int) config('omie.http.connect_timeout', 10),
                'headers' => ['Content-Type' => 'application/json'],
            ]));
        }

        $this->app->singleton(OmieRateLimiter::class, fn ($app) => new OmieRateLimiter(
            $app->make(CacheRepository::class),
            (array) config('omie', [])
        ));

        $this->app->singleton(OmieApiLogger::class);
        $this->app->singleton(OmieManager::class);
        $this->app->alias(OmieManager::class, 'omie');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([
            __DIR__ . '/../config/omie.php' => config_path('omie.php'),
        ], 'omie-config');
    }
}
