<?php

namespace Core\Providers\Exceptions;

use RuntimeException;

class UnknownProviderException extends RuntimeException
{
    public static function forServer(string $key): self
    {
        return new self("Unknown server provider [{$key}].");
    }

    public static function forNode(string $key): self
    {
        return new self("Unknown node provider [{$key}].");
    }

    public static function forPaymentGateway(string $key): self
    {
        return new self("Unknown payment gateway provider [{$key}].");
    }
}
