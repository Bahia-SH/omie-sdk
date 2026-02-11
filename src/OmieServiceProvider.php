<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Logging\OmieApiLogger;
use Bahiash\Omie\Services\ProdutosService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class OmieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/omie.php',
            'omie'
        );

        if (! $this->app->bound(ClientInterface::class)) {
            $this->app->singleton(ClientInterface::class, function () {
                $baseUrl = \function_exists('config') ? \call_user_func('config', 'omie.base_url') : null;
                $baseUrl = $baseUrl ?: 'https://app.omie.com.br/api/v1/';

                return new GuzzleClient([
                    'base_uri' => $baseUrl,
                    'timeout' => 30,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);
            });
        }

        $this->app->singleton(OmieRateLimiter::class, function ($app) {
            /** @var CacheRepository $cache */
            $cache = $app->make(CacheRepository::class);

            $config = \function_exists('config') ? \call_user_func('config', 'omie') : require __DIR__ . '/../config/omie.php';

            return new OmieRateLimiter($cache, $config);
        });

        $this->app->singleton(OmieApiLogger::class, function () {
            return new OmieApiLogger();
        });

        $this->app->singleton(ProdutosService::class, function () {
            return new ProdutosService();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $target = \function_exists('config_path')
                ? \call_user_func('config_path', 'omie.php')
                : 'config/omie.php';

            $this->publishes([
                __DIR__ . '/../config/omie.php' => $target,
            ], 'omie-config');
        }
    }
}
