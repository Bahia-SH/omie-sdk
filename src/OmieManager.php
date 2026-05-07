<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\Services\AbstractOmieService;
use Bahiash\Omie\Services\CategoriasService;
use Bahiash\Omie\Services\ClientesService;
use Bahiash\Omie\Services\ContasPagarService;
use Bahiash\Omie\Services\ContasReceberService;
use Bahiash\Omie\Services\DepartamentosService;
use Bahiash\Omie\Services\GenericService;
use Bahiash\Omie\Services\NfeService;
use Bahiash\Omie\Services\PedidosService;
use Bahiash\Omie\Services\ProdutosService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;

/**
 * @method ProdutosService produtos()
 * @method ClientesService clientes()
 * @method PedidosService pedidos()
 * @method NfeService nfe()
 * @method ContasPagarService contasPagar()
 * @method ContasReceberService contasReceber()
 * @method CategoriasService categorias()
 * @method DepartamentosService departamentos()
 * @method GenericService generic()
 */
class OmieManager
{
    /** @var array<string, class-string<AbstractOmieService>> */
    public const SERVICES = [
        'produtos' => ProdutosService::class,
        'clientes' => ClientesService::class,
        'pedidos' => PedidosService::class,
        'nfe' => NfeService::class,
        'contasPagar' => ContasPagarService::class,
        'contasReceber' => ContasReceberService::class,
        'categorias' => CategoriasService::class,
        'departamentos' => DepartamentosService::class,
        'generic' => GenericService::class,
    ];

    public function __construct(protected Container $container)
    {
    }

    public function for(string $appKey, string $appSecret, ?string $tenantId = null): OmieTenant
    {
        return new OmieTenant($this->container, $appKey, $appSecret, $tenantId);
    }

    public function __call(string $name, array $arguments): AbstractOmieService
    {
        $class = self::SERVICES[$name] ?? null;
        if ($class === null) {
            throw new \BadMethodCallException("Serviço Omie '{$name}' inexistente.");
        }

        return $this->container->make($class);
    }

    public function findLog(string $correlationId): ?OmieApiLog
    {
        return OmieApiLog::findByCorrelationId($correlationId);
    }

    /**
     * @throws \RuntimeException
     */
    public function waitFor(string $correlationId, ?int $timeoutSeconds = null, ?int $pollMs = null): OmieApiLog
    {
        $timeoutSeconds ??= (int) Config::get('omie.sync.wait_timeout_seconds', 30);
        $pollMs ??= (int) Config::get('omie.sync.wait_poll_ms', 250);

        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $log = OmieApiLog::findByCorrelationId($correlationId);
            if ($log !== null && $log->isFinished()) {
                return $log;
            }
            usleep($pollMs * 1000);
        }

        throw new \RuntimeException('Timeout aguardando log Omie correlation_id=' . $correlationId);
    }
}
