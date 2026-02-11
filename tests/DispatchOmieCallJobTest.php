<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\Jobs\DispatchOmieCallJob;
use Bahiash\Omie\Logging\OmieApiLogger;
use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\OmieRateLimiter;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class DispatchOmieCallJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    protected function createMockGuzzleResponse(string $body): ResponseInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        return $response;
    }

    protected function createMockHttpClient(ResponseInterface $response): ClientInterface&MockObject
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with('POST', $this->anything(), $this->anything())
            ->willReturn($response);
        return $http;
    }

    public function test_handle_chama_rate_limiter_client_e_registra_log_sucesso(): void
    {
        $responseBody = json_encode([
            'codigo_status' => '0',
            'descricao_status' => 'Ok',
            'lista_produtos' => [],
        ]);
        $http = $this->createMockHttpClient($this->createMockGuzzleResponse($responseBody));

        $rateLimiter = $this->createMock(OmieRateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('checkOrWait')
            ->with('key1', 'ListarProdutos', null);

        $logger = $this->createMock(OmieApiLogger::class);
        $log = new OmieApiLog(['app_key' => 'key1', 'service_path' => 'geral/produtos', 'method' => 'ListarProdutos']);
        $log->id = 1;
        $logger->expects($this->once())->method('startLog')->willReturn($log);
        $logger->expects($this->once())->method('finishLogSuccess')->with(
            $log,
            $this->callback(fn ($r) => $r['codigo_status'] === '0'),
            200,
            $this->callback(fn ($e) => isset($e['duration_ms']) && isset($e['omie_status_code']))
        );
        $logger->expects($this->never())->method('finishLogError');

        $this->app->instance(OmieRateLimiter::class, $rateLimiter);
        $this->app->instance(OmieApiLogger::class, $logger);

        $job = new DispatchOmieCallJob('key1', 'secret1', 'geral/produtos', 'ListarProdutos', ['pagina' => 1]);
        $job->handle($rateLimiter, $logger, $http);
    }

    public function test_handle_em_erro_registra_log_error_e_relanca_exception(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())->method('request')->willThrowException(new \RuntimeException('Network error'));

        $rateLimiter = $this->createMock(OmieRateLimiter::class);
        $rateLimiter->method('checkOrWait');

        $log = new OmieApiLog(['app_key' => 'key1', 'service_path' => 'geral/produtos', 'method' => 'ListarProdutos']);
        $log->id = 1;
        $logger = $this->createMock(OmieApiLogger::class);
        $logger->method('startLog')->willReturn($log);
        $logger->expects($this->once())->method('finishLogError')->with(
            $log,
            $this->isInstanceOf(\RuntimeException::class),
            null,
            $this->callback(fn ($e) => isset($e['duration_ms']))
        );

        $job = new DispatchOmieCallJob('key1', 'secret1', 'geral/produtos', 'ListarProdutos', []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network error');

        $job->handle($rateLimiter, $logger, $http);
    }

    public function test_handle_dispara_evento_quando_event_class_definido(): void
    {
        Event::fake();

        $responseBody = json_encode(['codigo_status' => '0']);
        $http = $this->createMockHttpClient($this->createMockGuzzleResponse($responseBody));

        $rateLimiter = $this->createMock(OmieRateLimiter::class);
        $rateLimiter->method('checkOrWait');

        $log = OmieApiLog::create([
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => [],
        ]);
        $logger = $this->createMock(OmieApiLogger::class);
        $logger->method('startLog')->willReturn($log);
        $logger->method('finishLogSuccess');

        $job = new DispatchOmieCallJob(
            'key1',
            'secret1',
            'geral/produtos',
            'ListarProdutos',
            [],
            \Bahiash\Omie\Tests\Stubs\OmieCallCompletedEvent::class,
            ['custom' => 'data']
        );

        $job->handle($rateLimiter, $logger, $http);

        Event::assertDispatched(\Bahiash\Omie\Tests\Stubs\OmieCallCompletedEvent::class, function ($event) use ($log) {
            return $event->log->id === $log->id;
        });
    }

    public function test_handle_nao_dispara_evento_quando_event_class_null(): void
    {
        Event::fake();

        $responseBody = json_encode(['codigo_status' => '0']);
        $http = $this->createMockHttpClient($this->createMockGuzzleResponse($responseBody));

        $rateLimiter = $this->createMock(OmieRateLimiter::class);
        $rateLimiter->method('checkOrWait');

        $log = OmieApiLog::create([
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => [],
        ]);
        $logger = $this->createMock(OmieApiLogger::class);
        $logger->method('startLog')->willReturn($log);
        $logger->method('finishLogSuccess');

        $job = new DispatchOmieCallJob('key1', 'secret1', 'geral/produtos', 'ListarProdutos', [], null, []);

        $job->handle($rateLimiter, $logger, $http);

        Event::assertNotDispatched(\Bahiash\Omie\Tests\Stubs\OmieCallCompletedEvent::class);
    }

    public function test_handle_nao_dispara_evento_quando_event_class_nao_existe(): void
    {
        Event::fake([\Bahiash\Omie\Tests\Stubs\OmieCallCompletedEvent::class]);

        $responseBody = json_encode(['codigo_status' => '0']);
        $http = $this->createMockHttpClient($this->createMockGuzzleResponse($responseBody));

        $rateLimiter = $this->createMock(OmieRateLimiter::class);
        $rateLimiter->method('checkOrWait');

        $log = OmieApiLog::create([
            'app_key' => 'key1',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => [],
        ]);
        $logger = $this->createMock(OmieApiLogger::class);
        $logger->method('startLog')->willReturn($log);
        $logger->method('finishLogSuccess');

        $job = new DispatchOmieCallJob(
            'key1',
            'secret1',
            'geral/produtos',
            'ListarProdutos',
            [],
            'Classe\Inexistente\Event',
            []
        );

        $job->handle($rateLimiter, $logger, $http);

        Event::assertNotDispatched(\Bahiash\Omie\Tests\Stubs\OmieCallCompletedEvent::class);
    }

    public function test_masked_fields_são_mascarados_no_request_body_do_log(): void
    {
        Config::set('omie.logging.masked_fields', ['app_key']);

        $responseBody = json_encode(['codigo_status' => '0']);
        $http = $this->createMockHttpClient($this->createMockGuzzleResponse($responseBody));

        $rateLimiter = $this->createMock(OmieRateLimiter::class);
        $rateLimiter->method('checkOrWait');

        $job = new DispatchOmieCallJob('key1', 'my-secret-value', 'geral/produtos', 'ListarProdutos', []);
        $job->handle(
            $rateLimiter,
            $this->app->make(OmieApiLogger::class),
            $http
        );

        $log = OmieApiLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('***', $log->request_body['app_key'] ?? null);
    }

    public function test_job_usar_connection_e_queue_da_config(): void
    {
        Config::set('omie.queue.connection', 'redis');
        Config::set('omie.queue.queue', 'omie');

        $job = new DispatchOmieCallJob('k', 's', 'geral/produtos', 'ListarProdutos', []);

        $this->assertSame('k', $job->appKey);
        $this->assertSame('geral/produtos', $job->servicePath);
        $this->assertSame('ListarProdutos', $job->method);
    }

    public function test_job_usa_base_url_da_config(): void
    {
        Config::set('omie.base_url', 'https://custom.omie.com.br/api/v1/');

        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://custom.omie.com.br/api/v1/geral/produtos/',
                $this->anything()
            )
            ->willReturn($this->createMockGuzzleResponse(json_encode(['codigo_status' => '0'])));

        $rateLimiter = $this->createMock(OmieRateLimiter::class);
        $rateLimiter->method('checkOrWait');

        $log = OmieApiLog::create([
            'app_key' => 'k',
            'service_path' => 'geral/produtos',
            'method' => 'ListarProdutos',
            'request_body' => [],
        ]);
        $logger = $this->createMock(OmieApiLogger::class);
        $logger->method('startLog')->willReturn($log);
        $logger->method('finishLogSuccess');

        $job = new DispatchOmieCallJob('k', 's', 'geral/produtos', 'ListarProdutos', []);
        $job->handle($rateLimiter, $logger, $http);
    }
}
