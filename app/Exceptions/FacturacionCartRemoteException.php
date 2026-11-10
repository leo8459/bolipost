<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class FacturacionCartRemoteException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly ?string $upstreamCode = null,
        private readonly ?string $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function upstreamCode(): ?string
    {
        return $this->upstreamCode;
    }

    public function retryAfter(): ?string
    {
        return $this->retryAfter;
    }

    public function isConnectionFailure(): bool
    {
        return $this->httpStatus === null;
    }
}
