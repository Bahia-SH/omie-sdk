<?php

namespace Bahiash\Omie\Exceptions;

class OmieApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $payload = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}

