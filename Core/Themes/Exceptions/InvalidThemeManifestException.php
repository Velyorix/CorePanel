<?php

namespace Core\Themes\Exceptions;

use JsonException;
use RuntimeException;

class InvalidThemeManifestException extends RuntimeException
{
    public static function missingManifest(string $directory): self
    {
        return new self("Theme [{$directory}] is missing theme.json.");
    }

    public static function unreadableManifest(string $directory): self
    {
        return new self("Theme [{$directory}] theme.json could not be read.");
    }

    public static function invalidJson(string $directory, JsonException $previous): self
    {
        return new self(
            "Theme [{$directory}] theme.json contains invalid JSON: {$previous->getMessage()}",
            previous: $previous,
        );
    }

    public static function invalidStructure(string $directory): self
    {
        return new self("Theme [{$directory}] theme.json must decode to an object.");
    }

    public static function missingName(string $directory): self
    {
        return new self("Theme [{$directory}] theme.json must define a non-empty name.");
    }
}
