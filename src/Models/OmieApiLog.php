<?php

namespace Bahiash\Omie\Models;

/**
 * @extends \Illuminate\Database\Eloquent\Model<array<string, mixed>>
 */
class OmieApiLog extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'omie_api_logs';

    protected $guarded = [];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
    ];
}

