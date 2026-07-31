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

    public static function fileExists(string $path): self
    {
        return new self("Theme file already exists at [{$path}].");
    }

    public static function themeRequired(): self
    {
        return new self('No theme specified. Pass --theme= or activate a theme first.');
    }

    public static function invalidAssetType(string $type): self
    {
        return new self("Asset type [{$type}] is invalid. Use css or js.");
    }
}
