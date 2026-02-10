<?php

namespace Bahiash\Omie\Services;

use Bahiash\Omie\Jobs\DispatchOmieCallJob;

class ProdutosService
{
    protected const SERVICE_PATH = 'geral/produtos';

    public function dispatchCall(
        string $appKey,
        string $appSecret,
        string $method,
        array $params = [],
        ?string $correlationId = null
    ): void {
        /** @phpstan-ignore-next-line Método dispatch é fornecido por Illuminate\Queue\Jobs helpers em runtime */
        DispatchOmieCallJob::dispatch(
            $appKey,
            $appSecret,
            self::SERVICE_PATH,
            $method,
            $params,
            $correlationId
        );
    }
}

