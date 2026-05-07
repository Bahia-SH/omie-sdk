<?php

namespace Bahiash\Omie\Exceptions;

class OmieApiException extends \RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $payload = null,
        public readonly ?string $omieFaultCode = null,
        public readonly ?string $omieFaultString = null,
        public readonly bool $retryable = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): ?int
    {
        return $this->statusCode;
    }

    public function getOmieFaultCode(): ?string
    {
        return $this->omieFaultCode;
    }

    public function getOmieFaultString(): ?string
    {
        return $this->omieFaultString;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function isRateLimited(): bool
    {
        if ($this->statusCode === 429 || $this->statusCode === 425) {
            return true;
        }

        $haystack = strtolower((string) ($this->omieFaultString ?? $this->getMessage()));

        return str_contains($haystack, 'too many requests')
            || str_contains($haystack, 'consumindo demais')
            || str_contains($haystack, 'limite de requisi');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromOmiePayload(array $payload, ?int $httpStatus = null): self
    {
        $faultCode = isset($payload['faultcode']) ? (string) $payload['faultcode'] : null;
        $faultString = isset($payload['faultstring']) ? (string) $payload['faultstring'] : null;

        $message = $faultString ?? ($payload['descricao_status'] ?? 'Erro retornado pela API Omie');

        $retryable = false;
        $haystack = strtolower($faultString ?? '');
        if (str_contains($haystack, 'too many requests')
            || str_contains($haystack, 'limite de requisi')
            || str_contains($haystack, 'consumindo demais')
            || ($httpStatus !== null && $httpStatus >= 500)) {
            $retryable = true;
        }

        return new self(
            (string) $message,
            $httpStatus,
            $payload,
            $faultCode,
            $faultString,
            $retryable
        );
    }
}
