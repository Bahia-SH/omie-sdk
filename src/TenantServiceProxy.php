<?php

namespace Bahiash\Omie;

use Bahiash\Omie\Models\OmieApiLog;
use Bahiash\Omie\Services\AbstractOmieService;

class TenantServiceProxy
{
    public function __construct(
        protected AbstractOmieService $service,
        protected string $appKey,
        protected string $appSecret,
        protected ?string $tenantId = null
    ) {
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $eventParams
     */
    public function dispatch(
        string $method,
        array $params = [],
        ?string $eventClass = null,
        array $eventParams = []
    ): string {
        return $this->service->dispatchCall(
            $this->appKey,
            $this->appSecret,
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
    public function call(string $method, array $params = []): OmieApiLog
    {
        return $this->service->call(
            $this->appKey,
            $this->appSecret,
            $method,
            $params,
            $this->tenantId
        );
    }
}
