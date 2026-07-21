<?php

namespace Core\Billing\Exceptions;

use RuntimeException;

class DisabledPaymentGatewayException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Payment gateway [{$key}] is disabled.");
    }
}
