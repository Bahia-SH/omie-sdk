<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\OmieClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class OmieClientTest extends TestCase
{
    protected function createMockResponse(int $statusCode, string $body): ResponseInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
    }

    protected function createHttpClient(ResponseInterface $response): ClientInterface&MockObject
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->callback(function (string $url) {
                    return str_contains($url, 'geral/produtos/') && str_starts_with($url, 'https://app.omie.com.br/api/v1/');
                }),
                $this->callback(function (array $options) {
                    return isset($options['json'])
                        && $options['json']['app_key'] === 'my-key'
                        && $options['json']['app_secret'] === 'my-secret'
                        && $options['json']['call'] === 'ListarProdutos'
                        && isset($options['json']['param']);
                })
            )
            ->willReturn($response);

        return $http;
    }

    public function test_call_envia_request_correto_e_retorna_array_decodificado(): void
    {
        $body = json_encode([
            'codigo_status' => '0',
            'descricao_status' => 'Ok',
            'lista_produtos' => [],
        ]);
        $response = $this->createMockResponse(200, $body);
        $http = $this->createHttpClient($response);

        $client = new OmieClient('my-key', 'my-secret', 'https://app.omie.com.br/api/v1/', $http);
        $result = $client->call('geral/produtos', 'ListarProdutos', ['pagina' => 1]);

        $this->assertIsArray($result);
        $this->assertSame('0', $result['codigo_status']);
        $this->assertSame('Ok', $result['descricao_status']);
        $this->assertSame([], $result['lista_produtos']);
    }

    public function test_call_normaliza_param_associativo_para_lista_com_um_elemento(): void
    {
        $body = json_encode(['codigo_status' => '0']);
        $response = $this->createMockResponse(200, $body);
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function (array $options) {
                    $param = $options['json']['param'];
                    return is_array($param) && isset($param[0]) && $param[0]['pagina'] === 1;
                })
            )
            ->willReturn($response);

        $client = new OmieClient('my-key', 'my-secret', 'https://app.omie.com.br/api/v1/', $http);
        $client->call('geral/produtos', 'ListarProdutos', ['pagina' => 1]);
    }

    public function test_call_mantém_param_lista_ou_vazio(): void
    {
        $body = json_encode(['codigo_status' => '0']);
        $response = $this->createMockResponse(200, $body);
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function (array $options) {
                    $param = $options['json']['param'];
                    return $param === [] || array_is_list($param);
                })
            )
            ->willReturn($response);

        $client = new OmieClient('my-key', 'my-secret', 'https://app.omie.com.br/api/v1/', $http);
        $client->call('geral/produtos', 'ListarProdutos', []);
    }

    public function test_base_url_recebe_trailing_slash_corretamente(): void
    {
        $body = json_encode(['codigo_status' => '0']);
        $response = $this->createMockResponse(200, $body);
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://app.omie.com.br/api/v1/geral/produtos/',
                $this->anything()
            )
            ->willReturn($response);

        $client = new OmieClient('key', 'secret', 'https://app.omie.com.br/api/v1', $http);
        $client->call('geral/produtos', 'ListarProdutos', []);
    }

    public function test_base_url_com_barra_no_fim_mantém_uma_barra(): void
    {
        $body = json_encode(['codigo_status' => '0']);
        $response = $this->createMockResponse(200, $body);
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://app.omie.com.br/api/v1/geral/produtos/',
                $this->anything()
            )
            ->willReturn($response);

        $client = new OmieClient('key', 'secret', 'https://app.omie.com.br/api/v1/', $http);
        $client->call('geral/produtos', 'ListarProdutos', []);
    }

    public function test_resposta_json_invalida_lanca_runtime_exception(): void
    {
        $response = $this->createMockResponse(200, 'not json');
        $http = $this->createHttpClient($response);

        $client = new OmieClient('my-key', 'my-secret', 'https://app.omie.com.br/api/v1/', $http);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Resposta inválida da API OMIE');

        $client->call('geral/produtos', 'ListarProdutos', []);
    }

    public function test_resposta_null_json_decode_retorna_array_raw(): void
    {
        $response = $this->createMockResponse(200, 'null');
        $http = $this->createHttpClient($response);

        $client = new OmieClient('my-key', 'my-secret', 'https://app.omie.com.br/api/v1/', $http);
        $result = $client->call('geral/produtos', 'ListarProdutos', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('raw', $result);
        $this->assertSame('null', $result['raw']);
    }

    public function test_guzzle_exception_é_repassada(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->willThrowException(new RequestException('Connection failed', $this->createStub(RequestInterface::class)));

        $client = new OmieClient('my-key', 'my-secret', 'https://app.omie.com.br/api/v1/', $http);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Connection failed');

        $client->call('geral/produtos', 'ListarProdutos', []);
    }
}
