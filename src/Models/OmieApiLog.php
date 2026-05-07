<?php

namespace Bahiash\Omie\Models;

/**
 * @extends \Illuminate\Database\Eloquent\Model<array<string, mixed>>
 *
 * @property int $id
 * @property string|null $correlation_id
 * @property string|null $tenant_id
 * @property string $status
 * @property int $attempt
 * @property string $app_key
 * @property string $service_path
 * @property string $method
 * @property array|null $request_body
 * @property array|null $response_body
 * @property int|null $http_status
 * @property string|null $omie_status_code
 * @property string|null $omie_status_message
 * @property string|null $omie_fault_code
 * @property string|null $omie_fault_string
 * @property bool $retryable
 * @property int|null $duration_ms
 * @property string|null $ip_origem
 * @property string|null $error_class
 * @property string|null $error_message
 * @property string|null $error_trace
 * @property string|null $event_class
 * @property array|null $event_params
 * @property \Carbon\Carbon|null $finished_at
 */
class OmieApiLog extends \Illuminate\Database\Eloquent\Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $table = 'omie_api_logs';

    protected $guarded = [];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
        'event_params' => 'array',
        'retryable' => 'bool',
        'finished_at' => 'datetime',
    ];

    public static function findByCorrelationId(string $correlationId): ?self
    {
        return static::where('correlation_id', $correlationId)->latest('id')->first();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED], true);
    }

    public function wasSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
