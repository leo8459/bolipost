<?php

namespace App\Exceptions;

use RuntimeException;

class FacturacionScanConflictException extends RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $matches
     */
    public function __construct(
        string $message,
        protected array $matches = []
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function matches(): array
    {
        return $this->matches;
    }
}
