<?php

namespace Bahiash\Omie\Services;

use Bahiash\Omie\Exceptions\OmieApiException;
use Bahiash\Omie\Models\OmieApiLog;

/**
 * Service genérico que aceita qualquer servicePath em runtime.
 */
class GenericService extends AbstractOmieService
{
    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $eventParams
     */
    public function dispatchCallTo(
        string $appKey,
        string $appSecret,
        string $servicePath,
        string $method,
        array $params = [],
        ?string $eventClass = null,
        array $eventParams = [],
        ?string $tenantId = null,
        ?string $ipOrigem = null
    ): string {
        return parent::dispatchCall(
            $appKey,
            $appSecret,
            $method,
            $params,
            $eventClass,
            $eventParams,
            $tenantId,
            $ipOrigem,
            $servicePath
        );
    }

    /**
     * @param  array<int|string, mixed>  $params
     *
     * @throws OmieApiException
     */
    public function callTo(
        string $appKey,
        string $appSecret,
        string $servicePath,
        string $method,
        array $params = [],
        ?string $tenantId = null,
        ?string $ipOrigem = null
    ): OmieApiLog {
        return parent::call(
            $appKey,
            $appSecret,
            $method,
            $params,
            $tenantId,
            $ipOrigem,
            $servicePath
        );
    }
}
