<?php

namespace Core\Modules\Exceptions;

use RuntimeException;

class ModuleScaffoldException extends RuntimeException
{
    public static function directoryExists(string $path): self
    {
        return new self("Module directory already exists at [{$path}].");
    }

    public static function keyExists(string $key): self
    {
        return new self("Module key [{$key}] is already registered.");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Unable to write module file at [{$path}].");
    }

    public static function fileExists(string $path): self
    {
        return new self("Module file already exists at [{$path}].");
    }

    public static function moduleRequired(): self
    {
        return new self('No module specified. Pass --module= with the target module key.');
    }

    public static function invalidMigrationName(string $name): self
    {
        return new self("Migration name [{$name}] is invalid.");
    }
}
