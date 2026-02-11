<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\Logging\OmieApiLogger;
use Bahiash\Omie\Models\OmieApiLog;

class OmieApiLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    public function test_start_log_cria_omie_api_log(): void
    {
        $logger = new OmieApiLogger();
        $data = [
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => ['pagina' => 1],
        ];

        $log = $logger->startLog($data);

        $this->assertInstanceOf(OmieApiLog::class, $log);
        $this->assertTrue($log->exists);
        $this->assertSame('key1', $log->app_key);
        $this->assertSame('geral/produtos', $log->service_path);
        $this->assertSame('ListarProdutos', $log->method);
        $this->assertSame(['pagina' => 1], $log->request_body);
    }

    public function test_finish_log_success_atualiza_log(): void
    {
        $logger = new OmieApiLogger();
        $log = $logger->startLog([
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => [],
        ]);

        $response = ['codigo_status' => '0', 'lista_produtos' => []];
        $logger->finishLogSuccess($log, $response, 200, [
            'duration_ms' => 150,
            'omie_status_code' => '0',
            'omie_status_message' => 'Ok',
        ]);

        $log->refresh();
        $this->assertSame($response, $log->response_body);
        $this->assertSame(200, $log->http_status);
        $this->assertNull($log->error_class);
        $this->assertNull($log->error_message);
        $this->assertSame(150, $log->duration_ms);
        $this->assertSame('0', $log->omie_status_code);
        $this->assertSame('Ok', $log->omie_status_message);
    }

    public function test_finish_log_error_atualiza_log_com_exception(): void
    {
        $logger = new OmieApiLogger();
        $log = $logger->startLog([
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => [],
        ]);

        $e = new \RuntimeException('Connection timeout');
        $logger->finishLogError($log, $e, null, ['duration_ms' => 5000]);

        $log->refresh();
        $this->assertNull($log->response_body);
        $this->assertSame(\RuntimeException::class, $log->error_class);
        $this->assertSame('Connection timeout', $log->error_message);
        $this->assertNotEmpty($log->error_trace);
        $this->assertSame(5000, $log->duration_ms);
    }

    public function test_finish_log_error_pode_receber_partial_response(): void
    {
        $logger = new OmieApiLogger();
        $log = $logger->startLog([
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => [],
        ]);

        $e = new \RuntimeException('Error');
        $partial = ['faultcode' => 'Server', 'faultstring' => 'Internal error'];
        $logger->finishLogError($log, $e, $partial, []);

        $log->refresh();
        $this->assertSame($partial, $log->response_body);
    }
}
