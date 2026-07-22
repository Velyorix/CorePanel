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

    public static function selfParent(string $directory): self
    {
        return new self("Theme [{$directory}] theme.json cannot use itself as parent.");
    }

    public static function unknownParent(string $directory, string $parentKey): self
    {
        return new self("Theme [{$directory}] theme.json references unknown parent [{$parentKey}].");
    }

    public static function circularParent(string $directory): self
    {
        return new self("Theme [{$directory}] theme.json parent chain contains a cycle.");
    }

    public static function invalidViewsPath(string $directory): self
    {
        return new self("Theme [{$directory}] theme.json views path must be a non-empty string.");
    }
}
