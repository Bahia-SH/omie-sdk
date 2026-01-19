<?php

namespace Bahiash\Omie\Services;

use Bahiash\Omie\OmieClient;

class AjusteEstoqueService
{
    protected const SERVICE_PATH = 'geral/ajustesestoque';

    public function __construct(
        protected OmieClient $client
    ) {}

    /**
     * Altera o estoque mínimo de um produto.
     *
     * @param  array  $param  Ex: ['nCodProd' => 123, 'nEstMin' => 10] ou conforme documentação OMIE
     * @return array Resposta da API
     */
    public function alterarEstoqueMinimo(array $param): array
    {
        return $this->client->call(self::SERVICE_PATH, 'AlterarEstoqueMinimo', $param);
    }

    /**
     * Exclui um ajuste de estoque.
     *
     * @param  array  $param  Ex: ['nCodMov' => 12345] ou identificador do ajuste conforme documentação OMIE
     * @return array Resposta da API
     */
    public function excluirAjusteEstoque(array $param): array
    {
        return $this->client->call(self::SERVICE_PATH, 'ExcluirAjusteEstoque', $param);
    }

    /**
     * Inclui um ajuste de estoque (entrada ou saída).
     *
     * Parâmetros típicos:
     * - codigo_local_estoque (int): Código do local de estoque
     * - id_prod (int): ID do produto
     * - data (string): Data do ajuste (dd/MM/yyyy)
     * - quan (string|int): Quantidade
     * - tipo (string): "ENT" (entrada) ou "SAI" (saída)
     * - origem (string): Ex. "AJU"
     * - motivo (string): Ex. "INV" (inventário)
     * - obs (string, opcional): Observação
     * - valor (float, opcional): Valor unitário
     * - lote_validade (array, opcional): Lotes quando há controle de lote/validade
     *
     * @param  array  $param
     * @return array Resposta da API (ex.: codigo_status, descricao_status, nCodMov, etc.)
     */
    public function incluirAjusteEstoque(array $param): array
    {
        return $this->client->call(self::SERVICE_PATH, 'IncluirAjusteEstoque', $param);
    }

    /**
     * Lista ajustes de estoque com filtros e paginação.
     *
     * Parâmetros típicos:
     * - nPagina (int): Página
     * - nRegPorPagina (int): Registros por página
     * - dDataInicial (string, opcional): Data inicial (dd/MM/yyyy)
     * - dDataFinal (string, opcional): Data final (dd/MM/yyyy)
     * - nCodProd (int, opcional): Código do produto
     * - nCodLocal (int, opcional): Código do local de estoque
     *
     * @param  array  $param
     * @return array Resposta da API (listagem; sujeita a cache de 60s se configurado)
     */
    public function listarAjusteEstoque(array $param = []): array
    {
        return $this->client->call(self::SERVICE_PATH, 'ListarAjusteEstoque', $param);
    }
}
