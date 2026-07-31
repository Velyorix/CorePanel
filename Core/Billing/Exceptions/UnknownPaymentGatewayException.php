<?php

namespace Core\Billing\Exceptions;

use RuntimeException;

class UnknownPaymentGatewayException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Unknown payment gateway [{$key}].");
    }
}
