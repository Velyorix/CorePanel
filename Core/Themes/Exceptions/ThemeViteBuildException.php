<?php

namespace Core\Themes\Exceptions;

use RuntimeException;

class ThemeViteBuildException extends RuntimeException
{
    public static function themeNotFound(string $key): self
    {
        return new self("Theme [{$key}] was not found.");
    }

    public static function processFailed(string $command, int $exitCode): self
    {
        return new self("Vite command [{$command}] failed with exit code {$exitCode}.");
    }

    public static function binaryMissing(string $binary): self
    {
        return new self("Unable to locate [{$binary}]. Install Node.js/npm and run npm install in the project root.");
    }
}
