<?php

namespace Core\Modules\Support;

use Illuminate\Support\Str;

/**
 * Normalizes user input into module package directory and manifest identifiers.
 */
final class ModuleName
{
    public function __construct(
        public string $directory,
        public string $key,
        public string $label,
        public string $namespace,
    ) {
    }

    public static function fromInput(string $name, ?string $key = null, ?string $label = null): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Module name cannot be empty.');
        }

        $directory = Str::studly(str_replace(['-', '_'], ' ', $trimmed));
        $resolvedKey = self::normalizeKey($key ?? Str::snake($directory));
        $resolvedLabel = trim((string) ($label ?? Str::headline($directory)));

        if ($resolvedLabel === '') {
            $resolvedLabel = Str::headline($directory);
        }

        return new self(
            directory: $directory,
            key: $resolvedKey,
            label: $resolvedLabel,
            namespace: 'Modules\\'.$directory,
        );
    }

    public function moduleClass(): string
    {
        return $this->namespace.'\\'.$this->directory.'Module';
    }

    public function serviceProviderClass(): string
    {
        return $this->namespace.'\\Providers\\'.$this->directory.'ServiceProvider';
    }

    public function studly(string $suffix): string
    {
        return $this->directory.$suffix;
    }

    private static function normalizeKey(string $key): string
    {
        $key = strtolower(trim(str_replace('-', '_', $key)));

        if ($key === '' || ! preg_match('/^[a-z][a-z0-9_-]*$/', $key)) {
            throw new \InvalidArgumentException('Module key is invalid. Use lowercase letters, numbers, underscores, or hyphens.');
        }

        return $key;
    }
}
