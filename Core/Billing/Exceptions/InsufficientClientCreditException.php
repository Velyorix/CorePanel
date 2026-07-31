<?php

namespace Core\Billing\Exceptions;

use RuntimeException;

class InsufficientClientCreditException extends RuntimeException
{
    public static function forAmount(string $requested, string $available): self
    {
        return new self(
            "Insufficient client credit: requested {$requested}, available {$available}.",
        );
    }
}
