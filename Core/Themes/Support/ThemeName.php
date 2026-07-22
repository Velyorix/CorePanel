<?php

namespace Core\Themes\Support;

use Illuminate\Support\Str;

/**
 * Normalizes user input into theme package directory and manifest identifiers.
 */
final class ThemeName
{
    public function __construct(
        public string $directory,
        public string $key,
        public string $label,
    ) {
    }

    public static function fromInput(string $name, ?string $key = null, ?string $label = null): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Theme name cannot be empty.');
        }

        $directory = Str::studly(str_replace(['-', '_'], ' ', $trimmed));
        $resolvedKey = self::normalizeKey($key ?? Str::kebab($directory));
        $resolvedLabel = trim((string) ($label ?? Str::headline($directory)));

        if ($resolvedKey === '') {
            throw new \InvalidArgumentException('Theme key cannot be empty.');
        }

        if ($resolvedLabel === '') {
            $resolvedLabel = Str::headline($directory);
        }

        return new self(
            directory: $directory,
            key: $resolvedKey,
            label: $resolvedLabel,
        );
    }

    private static function normalizeKey(string $key): string
    {
        return Str::kebab(trim(str_replace('_', '-', $key)));
    }
}
