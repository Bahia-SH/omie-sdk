<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\Models\OmieApiLog;

class OmieApiLogModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    public function test_table_name(): void
    {
        $log = new OmieApiLog;
        $this->assertSame('omie_api_logs', $log->getTable());
    }

    public function test_guarded_vazio_permite_mass_assignment(): void
    {
        $log = OmieApiLog::create([
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => ['pagina' => 1],
            'response_body' => ['codigo_status' => '0'],
            'http_status' => 200,
        ]);

        $this->assertSame('key1', $log->app_key);
        $this->assertSame(['pagina' => 1], $log->request_body);
        $this->assertSame(['codigo_status' => '0'], $log->response_body);
        $this->assertSame(200, $log->http_status);
    }

    public function test_casts_request_body_response_body_event_params_as_array(): void
    {
        $log = OmieApiLog::create([
            'app_key' => 'k',
            'service_path' => 's',
            'method' => 'm',
            'request_body' => ['a' => 1],
            'response_body' => ['b' => 2],
            'event_params' => ['c' => 3],
        ]);

        $this->assertIsArray($log->request_body);
        $this->assertSame(['a' => 1], $log->request_body);
        $this->assertIsArray($log->response_body);
        $this->assertSame(['b' => 2], $log->response_body);
        $this->assertIsArray($log->event_params);
        $this->assertSame(['c' => 3], $log->event_params);
    }
}
