<?php

namespace Bahiash\Omie\Logging;

use Bahiash\Omie\Exceptions\OmieApiException;
use Bahiash\Omie\Models\OmieApiLog;
use Illuminate\Support\Facades\Config;

class OmieApiLogger
{
    /** @var array<string, true>|null */
    protected ?array $maskedFieldsLookup = null;

    /**
     * @param  array<string, mixed>  $data
     */
    public function startLog(array $data): OmieApiLog
    {
        $data['status'] ??= OmieApiLog::STATUS_RUNNING;

        if (isset($data['request_body']) && is_array($data['request_body'])) {
            $data['request_body'] = $this->mask($data['request_body']);
        }
        if (isset($data['event_params']) && is_array($data['event_params'])) {
            $data['event_params'] = $this->mask($data['event_params']);
        }

        return OmieApiLog::create($data);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $extra
     */
    public function finishLogSuccess(OmieApiLog $log, array $response, int $httpStatus, array $extra = []): void
    {
        $maskResponse = (bool) Config::get('omie.logging.mask_response', true);

        $log->fill([
            'status' => OmieApiLog::STATUS_SUCCESS,
            'response_body' => $maskResponse ? $this->mask($response) : $response,
            'http_status' => $httpStatus,
            'error_class' => null,
            'error_message' => null,
            'error_trace' => null,
            'finished_at' => now(),
        ] + $extra);

        $log->save();
    }

    /**
     * @param  array<string, mixed>|null  $partialResponse
     * @param  array<string, mixed>  $extra
     */
    public function finishLogError(OmieApiLog $log, \Throwable $e, ?array $partialResponse = null, array $extra = []): void
    {
        $maskResponse = (bool) Config::get('omie.logging.mask_response', true);

        $payload = [
            'status' => OmieApiLog::STATUS_FAILED,
            'response_body' => $partialResponse !== null && $maskResponse ? $this->mask($partialResponse) : $partialResponse,
            'error_class' => $e::class,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
            'finished_at' => now(),
        ];

        if ($e instanceof OmieApiException) {
            $payload['http_status'] = $e->getHttpStatus();
            $payload['omie_fault_code'] = $e->getOmieFaultCode();
            $payload['omie_fault_string'] = $e->getOmieFaultString();
            $payload['retryable'] = $e->isRetryable();
        }

        $log->fill($payload + $extra);
        $log->save();
    }

    /**
     * Mascara recursivamente chaves sensíveis em arrays aninhados.
     *
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    public function mask(array $data): array
    {
        if ($this->maskedFieldsLookup === null) {
            $fields = (array) Config::get('omie.logging.masked_fields', []);
            $this->maskedFieldsLookup = [];
            foreach ($fields as $field) {
                $this->maskedFieldsLookup[strtolower((string) $field)] = true;
            }
        }

        return $this->maskRecursive($data, $this->maskedFieldsLookup);
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<string, true>  $lookup
     * @return array<int|string, mixed>
     */
    protected function maskRecursive(array $data, array $lookup): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && isset($lookup[strtolower($key)])) {
                $data[$key] = '***';
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->maskRecursive($value, $lookup);
            }
        }

        return $data;
    }
}
