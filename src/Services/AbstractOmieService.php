<?php

namespace Bahiash\Omie\Services;

use Bahiash\Omie\Exceptions\OmieApiException;
use Bahiash\Omie\Jobs\DispatchOmieCallJob;
use Bahiash\Omie\Models\OmieApiLog;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

abstract class AbstractOmieService
{
    public const SERVICE_PATH = '';

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<string, mixed>  $eventParams
     */
    public function dispatchCall(
        string $appKey,
        string $appSecret,
        string $method,
        array $params = [],
        ?string $eventClass = null,
        array $eventParams = [],
        ?string $tenantId = null,
        ?string $ipOrigem = null,
        ?string $servicePath = null
    ): string {
        $correlationId = (string) Str::uuid();

        DispatchOmieCallJob::dispatch(
            $appKey,
            $appSecret,
            $servicePath ?? static::SERVICE_PATH,
            $method,
            $params,
            $eventClass,
            $eventParams,
            $correlationId,
            $tenantId,
            $ipOrigem ?? $this->detectIp()
        );

        return $correlationId;
    }

    /**
     * @param  array<int|string, mixed>  $params
     *
     * @throws OmieApiException
     */
    public function call(
        string $appKey,
        string $appSecret,
        string $method,
        array $params = [],
        ?string $tenantId = null,
        ?string $ipOrigem = null,
        ?string $servicePath = null
    ): OmieApiLog {
        if (! (bool) Config::get('omie.sync.enabled', true)) {
            throw new \LogicException('Modo síncrono desabilitado. Habilite omie.sync.enabled.');
        }

        $correlationId = (string) Str::uuid();

        DispatchOmieCallJob::dispatchSync(
            $appKey,
            $appSecret,
            $servicePath ?? static::SERVICE_PATH,
            $method,
            $params,
            null,
            [],
            $correlationId,
            $tenantId,
            $ipOrigem ?? $this->detectIp()
        );

        $log = OmieApiLog::findByCorrelationId($correlationId);
        if ($log === null) {
            throw new OmieApiException('Log síncrono Omie não encontrado para correlation_id ' . $correlationId);
        }

        return $log;
    }

    protected function detectIp(): ?string
    {
        return rescue(fn () => App::bound('request') ? request()->ip() : null, null, false);
    }
}
