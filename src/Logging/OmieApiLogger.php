<?php

namespace Bahiash\Omie\Logging;

use Bahiash\Omie\Models\OmieApiLog;

class OmieApiLogger
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function startLog(array $data): OmieApiLog
    {
        return OmieApiLog::create($data);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function finishLogSuccess(OmieApiLog $log, array $response, int $httpStatus, array $extra = []): void
    {
        $log->fill([
            'response_body' => $response,
            'http_status' => $httpStatus,
            'error_class' => null,
            'error_message' => null,
            'error_trace' => null,
        ] + $extra);

        $log->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function finishLogError(OmieApiLog $log, \Throwable $e, ?array $partialResponse = null, array $extra = []): void
    {
        $log->fill([
            'response_body' => $partialResponse,
            'error_class' => $e::class,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
        ] + $extra);

        $log->save();
    }
}

