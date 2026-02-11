<?php

namespace Bahiash\Omie\Tests\Stubs;

use Bahiash\Omie\Models\OmieApiLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OmieCallCompletedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public OmieApiLog $log,
        public array $params = []
    ) {
    }
}
