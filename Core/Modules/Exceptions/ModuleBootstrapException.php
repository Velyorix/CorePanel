<?php

namespace Core\Modules\Exceptions;

use RuntimeException;

class ModuleBootstrapException extends RuntimeException
{
    public static function classMissing(string $key, string $class): self
    {
        return new self("Module [{$key}] declares class [{$class}] which could not be found.");
    }

    public static function invalidInstance(string $key, string $class): self
    {
        return new self("Module [{$key}] class [{$class}] must implement ModuleInterface.");
    }

    public static function requirementFailed(string $key, string $message): self
    {
        return new self("Module [{$key}] requirements are not met: {$message}");
    }

    public static function providerMissing(string $key, string $class): self
    {
        return new self("Module [{$key}] declares provider [{$class}] which could not be found.");
    }

    public static function invalidProvider(string $key, string $class): self
    {
        return new self("Module [{$key}] provider [{$class}] must extend Illuminate\\Support\\ServiceProvider.");
    }

    public static function gatewayMissing(string $key, string $class): self
    {
        return new self("Module [{$key}] declares gateway [{$class}] which could not be found.");
    }

    public static function invalidGateway(string $key, string $class): self
    {
        return new self("Module [{$key}] gateway [{$class}] must implement Core\\Billing\\Contracts\\PaymentGateway.");
    }
}
