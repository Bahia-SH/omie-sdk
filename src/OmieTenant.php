<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\Services\GenericService;
use Illuminate\Contracts\Container\Container;

/**
 * @method TenantServiceProxy produtos()
 * @method TenantServiceProxy clientes()
 * @method TenantServiceProxy pedidos()
 * @method TenantServiceProxy nfe()
 * @method TenantServiceProxy contasPagar()
 * @method TenantServiceProxy contasReceber()
 * @method TenantServiceProxy categorias()
 * @method TenantServiceProxy departamentos()
 */
class OmieTenant
{
    public function __construct(
        protected Container $container,
        public readonly string $appKey,
        public readonly string $appSecret,
        public readonly ?string $tenantId = null
    ) {
    }

    public function __call(string $name, array $arguments): TenantServiceProxy
    {
        $class = OmieManager::SERVICES[$name] ?? null;
        if ($class === null || $class === GenericService::class) {
            throw new \BadMethodCallException("Serviço Omie '{$name}' inexistente.");
        }

        return new TenantServiceProxy(
            $this->container->make($class),
            $this->appKey,
            $this->appSecret,
            $this->tenantId
        );
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $eventParams
     */
    public function dispatch(
        string $servicePath,
        string $method,
        array $params = [],
        ?string $eventClass = null,
        array $eventParams = []
    ): string {
        /** @var GenericService $generic */
        $generic = $this->container->make(GenericService::class);

        return $generic->dispatchCallTo(
            $this->appKey,
            $this->appSecret,
            $servicePath,
            $method,
            $params,
            $eventClass,
            $eventParams,
            $this->tenantId
        );
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function call(string $servicePath, string $method, array $params = []): OmieApiLog
    {
        /** @var GenericService $generic */
        $generic = $this->container->make(GenericService::class);

        return $generic->callTo(
            $this->appKey,
            $this->appSecret,
            $servicePath,
            $method,
            $params,
            $this->tenantId
        );
    }
}
