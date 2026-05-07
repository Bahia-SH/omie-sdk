<?php

namespace Bahiash\Omie\Exceptions;

class OmieRateLimitExceededException extends OmieApiException
{
    public function __construct(
        string $message = 'Limite de requisições da API Omie excedido.',
        ?int $statusCode = 429,
        ?array $payload = null,
        ?string $omieFaultCode = null,
        ?string $omieFaultString = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $statusCode,
            $payload,
            $omieFaultCode,
            $omieFaultString,
            true,
            $previous
        );
    }
}
