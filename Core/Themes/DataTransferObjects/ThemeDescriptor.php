<?php

namespace Core\Themes\DataTransferObjects;

use Core\Themes\Exceptions\InvalidThemeManifestException;
use JsonException;

/**
 * Parsed theme package metadata (minimal schema until full manifest in a later task).
 */
final readonly class ThemeDescriptor
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $version,
        public string $path,
        public ?string $description = null,
        public array $raw = [],
    ) {
    }

    public function manifestPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'theme.json';
    }

    public function viewsPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';
    }

    public function assetsPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'assets';
    }

    public function hasViews(): bool
    {
        return is_dir($this->viewsPath());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->key,
            'label' => $this->label !== $this->key ? $this->label : null,
            'version' => $this->version,
            'description' => $this->description,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @throws InvalidThemeManifestException
     * @throws JsonException
     */
    public static function fromDirectory(string $directory, ?string $directoryName = null): self
    {
        $directoryName ??= basename($directory);
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'theme.json';

        if (! is_file($manifestPath)) {
            throw InvalidThemeManifestException::missingManifest($directoryName);
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw InvalidThemeManifestException::unreadableManifest($directoryName);
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidThemeManifestException::invalidJson($directoryName, $exception);
        }

        if (! is_array($data)) {
            throw InvalidThemeManifestException::invalidStructure($directoryName);
        }

        $key = trim((string) ($data['name'] ?? $data['key'] ?? $directoryName));

        if ($key === '') {
            throw InvalidThemeManifestException::missingName($directoryName);
        }

        $label = trim((string) ($data['label'] ?? $data['title'] ?? $key));
        $version = trim((string) ($data['version'] ?? '1.0.0'));
        $description = isset($data['description']) && is_string($data['description'])
            ? trim($data['description'])
            : null;

        if ($description === '') {
            $description = null;
        }

        return new self(
            key: $key,
            label: $label !== '' ? $label : $key,
            version: $version !== '' ? $version : '1.0.0',
            path: $directory,
            description: $description,
            raw: $data,
        );
    }
}
