<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Services\AjusteEstoqueService;
use GuzzleHttp\Client;
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

        $this->app->singleton(OmieClient::class, function ($app) {
            $config = config('omie');
            $guzzle = $app->bound(ClientInterface::class)
                ? $app->make(ClientInterface::class)
                : new Client([
                    'base_uri' => $config['base_url'],
                    'timeout' => 30,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);

            $cache = $app->bound(CacheRepository::class)
                ? $app->make(CacheRepository::class)
                : null;

            return new OmieClient($config, $guzzle, $cache);
        });

        $this->app->alias(OmieClient::class, 'omie.client');

        $this->app->singleton(AjusteEstoqueService::class, function ($app) {
            return new AjusteEstoqueService($app->make(OmieClient::class));
        });

        $this->app->alias(AjusteEstoqueService::class, 'omie.ajuste_estoque');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/omie.php' => config_path('omie.php'),
            ], 'omie-config');
        }
    }
}
