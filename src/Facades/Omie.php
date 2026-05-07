<?php

namespace Bahiash\Omie\Facades;

use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\OmieManager;
use Bahiash\Omie\OmieTenant;
use Bahiash\Omie\Services\CategoriasService;
use Bahiash\Omie\Services\ClientesService;
use Bahiash\Omie\Services\ContasPagarService;
use Bahiash\Omie\Services\ContasReceberService;
use Bahiash\Omie\Services\DepartamentosService;
use Bahiash\Omie\Services\GenericService;
use Bahiash\Omie\Services\NfeService;
use Bahiash\Omie\Services\PedidosService;
use Bahiash\Omie\Services\ProdutosService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static OmieTenant for(string $appKey, string $appSecret, ?string $tenantId = null)
 * @method static ProdutosService produtos()
 * @method static ClientesService clientes()
 * @method static PedidosService pedidos()
 * @method static NfeService nfe()
 * @method static ContasPagarService contasPagar()
 * @method static ContasReceberService contasReceber()
 * @method static CategoriasService categorias()
 * @method static DepartamentosService departamentos()
 * @method static GenericService generic()
 * @method static OmieApiLog|null findLog(string $correlationId)
 * @method static OmieApiLog waitFor(string $correlationId, ?int $timeoutSeconds = null, ?int $pollMs = null)
 *
 * @see OmieManager
 */
class Omie extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OmieManager::class;
    }
}
