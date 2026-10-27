<?php

namespace App\Exceptions;

use RuntimeException;

class FacturacionSolicitudConfirmationRequired extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $solicitudCodigo,
        private readonly array $existing
    ) {
        parent::__construct($message);
    }

    public function solicitudCodigo(): string
    {
        return $this->solicitudCodigo;
    }

    public function existing(): array
    {
        return $this->existing;
    }
}
