<?php

namespace Bahiash\Omie\Events;

class OmieCallSucceeded extends OmieCallEvent
{
    public function wasSuccessful(): bool
    {
        return true;
    }
}
