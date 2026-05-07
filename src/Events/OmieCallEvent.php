<?php

namespace Bahiash\Omie\Events;

use Bahiash\Omie\Models\OmieApiLog;

abstract class OmieCallEvent
{
    /**
     * @param  array<string, mixed>  $eventParams
     */
    public function __construct(
        public readonly OmieApiLog $log,
        public readonly array $eventParams = []
    ) {
    }

    public function getLog(): OmieApiLog
    {
        return $this->log;
    }

    public function getCorrelationId(): ?string
    {
        return $this->log->correlation_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventParams(): array
    {
        return $this->eventParams;
    }

    abstract public function wasSuccessful(): bool;
}
