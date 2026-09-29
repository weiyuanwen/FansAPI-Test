<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class TransientUpstreamException extends RuntimeException
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?int $retryAfter = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
