<?php

namespace Core\Themes\Exceptions;

use RuntimeException;

class ThemeScaffoldException extends RuntimeException
{
    public static function directoryExists(string $path): self
    {
        return new self("Theme directory already exists at [{$path}].");
    }

    public static function keyExists(string $key): self
    {
        return new self("Theme key [{$key}] is already registered.");
    }

    public static function invalidName(string $name): self
    {
        return new self("Theme name [{$name}] is invalid.");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Unable to write theme file at [{$path}].");
    }
}
