<?php

namespace App\Domain\Assistant\Providers;

use RuntimeException;

final class ProviderException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly bool $retryable = false)
    {
        parent::__construct('El proveedor de IA no ha completado la consulta.');
    }
}
