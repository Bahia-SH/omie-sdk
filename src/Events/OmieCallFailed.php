<?php

namespace Bahiash\Omie\Events;

class OmieCallFailed extends OmieCallEvent
{
    public function wasSuccessful(): bool
    {
        return false;
    }
}
