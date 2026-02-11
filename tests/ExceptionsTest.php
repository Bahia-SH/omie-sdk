<?php

namespace Bahiash\Omie\Tests;

use Bahiash\Omie\Exceptions\OmieApiException;
use Bahiash\Omie\Exceptions\OmieRateLimitExceededException;

class ExceptionsTest extends TestCase
{
    public function test_omie_api_exception_armazena_status_code_e_payload(): void
    {
        $previous = new \RuntimeException('Previous');
        $e = new OmieApiException('Erro da API', 429, ['faultstring' => 'Too Many Requests'], $previous);

        $this->assertSame('Erro da API', $e->getMessage());
        $this->assertSame(429, $e->statusCode);
        $this->assertSame(['faultstring' => 'Too Many Requests'], $e->payload);
        $this->assertSame($previous, $e->getPrevious());
    }

    public function test_omie_api_exception_aceita_null_status_e_payload(): void
    {
        $e = new OmieApiException('Erro');

        $this->assertNull($e->statusCode);
        $this->assertNull($e->payload);
    }

    public function test_omie_rate_limit_exceeded_extends_omie_api_exception(): void
    {
        $e = new OmieRateLimitExceededException('Limite excedido.');

        $this->assertInstanceOf(OmieApiException::class, $e);
        $this->assertSame('Limite excedido.', $e->getMessage());
    }
}
