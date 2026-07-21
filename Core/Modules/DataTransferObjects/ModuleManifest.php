<?php

namespace Core\Modules\DataTransferObjects;

use Core\Modules\Exceptions\InvalidModuleManifestException;
use JsonException;

final readonly class ModuleManifest
{
    /**
     * @param  list<string>  $capabilities
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $version,
        public string $path,
        public array $capabilities = [],
        public ?string $description = null,
        public array $raw = [],
    ) {
    }

    public function manifestPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'module.json';
    }

    /**
     * @throws InvalidModuleManifestException
     * @throws JsonException
     */
    public static function fromDirectory(string $directory, ?string $fallbackKey = null): self
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR.'/\\');
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'module.json';

        if (! is_file($manifestPath)) {
            throw InvalidModuleManifestException::missing($directory);
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw InvalidModuleManifestException::unreadable($manifestPath);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return self::fromArray(
            $data,
            $directory,
            $fallbackKey ?? basename($directory),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidModuleManifestException
     */
    public static function fromArray(array $data, string $path, string $fallbackKey): self
    {
        $key = trim((string) ($data['name'] ?? $fallbackKey));

        if ($key === '') {
            throw InvalidModuleManifestException::missingField('name');
        }

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $key)) {
            throw InvalidModuleManifestException::invalidKey($key);
        }

        $version = trim((string) ($data['version'] ?? ''));

        if ($version === '') {
            throw InvalidModuleManifestException::missingField('version');
        }

        $capabilities = $data['capabilities'] ?? [];

        if (! is_array($capabilities)) {
            throw InvalidModuleManifestException::invalidCapabilities();
        }

        $normalizedCapabilities = [];

        foreach ($capabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                throw InvalidModuleManifestException::invalidCapabilities();
            }

            $normalizedCapabilities[] = trim($capability);
        }

        $label = trim((string) ($data['label'] ?? $key));
        $description = isset($data['description']) ? trim((string) $data['description']) : null;

        return new self(
            key: $key,
            name: $label !== '' ? $label : $key,
            version: $version,
            path: rtrim($path, DIRECTORY_SEPARATOR.'/\\'),
            capabilities: array_values(array_unique($normalizedCapabilities)),
            description: $description !== '' ? $description : null,
            raw: $data,
        );
    }
}
