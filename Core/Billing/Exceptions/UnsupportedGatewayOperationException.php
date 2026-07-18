<?php

namespace Core\Billing\Exceptions;

use RuntimeException;

class UnsupportedGatewayOperationException extends RuntimeException
{
    public static function forOperation(string $gatewayKey, string $operation): self
    {
        return new self("Payment gateway [{$gatewayKey}] does not support [{$operation}].");
    }
}
