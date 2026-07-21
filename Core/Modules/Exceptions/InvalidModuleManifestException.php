<?php

namespace Core\Modules\Exceptions;

use RuntimeException;

class InvalidModuleManifestException extends RuntimeException
{
    public static function missing(string $directory): self
    {
        return new self("Module manifest [module.json] was not found in [{$directory}].");
    }

    public static function unreadable(string $path): self
    {
        return new self("Unable to read module manifest at [{$path}].");
    }

    public static function missingField(string $field): self
    {
        return new self("Module manifest is missing required field [{$field}].");
    }

    public static function invalidKey(string $key): self
    {
        return new self("Module key [{$key}] is invalid. Use lowercase alphanumeric keys.");
    }

    public static function invalidCapabilities(): self
    {
        return new self('Module manifest [capabilities] must be a list of non-empty strings.');
    }
}
