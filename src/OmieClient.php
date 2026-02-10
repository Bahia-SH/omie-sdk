<?php

namespace Bahiash\Omie;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class OmieClient
{
    protected string $baseUrl;

    protected string $appKey;

    protected string $appSecret;

    protected ClientInterface $http;

    /**
     * Cria um cliente Omie para um conjunto específico de credenciais.
     *
     * As credenciais são sempre passadas por instância, permitindo multi-cliente.
     *
     * @param  string  $appKey
     * @param  string  $appSecret
     * @param  string  $baseUrl  Ex.: "https://app.omie.com.br/api/v1/"
     */
    public function __construct(string $appKey, string $appSecret, string $baseUrl, ClientInterface $http)
    {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->http = $http;
    }

    /**
     * Executa uma chamada à API OMIE.
     *
     * @param  string  $servicePath  Ex: "geral/ajustesestoque"
     * @param  string  $method  Ex: "IncluirAjusteEstoque"
     * @param  array  $param  Parâmetros (será enviado como param[0] se for um único objeto)
     * @return array Resposta JSON decodificada
     *
     * @throws \RuntimeException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function call(string $servicePath, string $method, array $param = []): array
    {
        $param = $this->normalizeParam($param);

        return $this->doRequest($servicePath, $method, $param);
    }

    /**
     * @param  array<string, mixed>  $param
     * @return array<int, mixed>
     */
    protected function normalizeParam(array $param): array
    {
        if (array_is_list($param) || $param === []) {
            return $param;
        }

        return [$param];
    }

    protected function doRequest(string $servicePath, string $method, array $param): array
    {
        $url = $this->baseUrl . ltrim($servicePath, '/');
        if (! str_ends_with($url, '/')) {
            $url .= '/';
        }

        $body = [
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret,
            'call' => $method,
            'param' => $param,
        ];

        $response = $this->http->request('POST', $url, [
            'json' => $body,
        ]);

        return $this->decodeResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida da API OMIE: ' . json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : ['raw' => $contents];
    }
}
