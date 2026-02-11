<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\OmieServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            OmieServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('omie.base_url', 'https://app.omie.com.br/api/v1/');
        $app['config']->set('omie.rate_limit.per_ip_per_minute', 960);
        $app['config']->set('omie.rate_limit.per_app_method_per_minute', 240);
        $app['config']->set('omie.rate_limit.concurrent_per_app_method', 4);
    }
}
