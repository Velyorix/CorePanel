<?php

namespace Core\Themes\DataTransferObjects;

use Core\Themes\Exceptions\InvalidThemeManifestException;
use Core\Themes\Support\ThemeAssetManifest;
use JsonException;

/**
 * Parsed theme.json metadata and resolved package paths.
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
        public string $viewsRelativePath = 'resources/views',
        public ?string $description = null,
        public ?string $author = null,
        public ?string $parent = null,
        /** @var list<string> Asset paths relative to the theme package root. */
        public array $assetEntries = [],
        public array $raw = [],
    ) {
    }

    public function manifestPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'theme.json';
    }

    public function viewsPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $this->viewsRelativePath,
        );
    }

    public function assetsPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'assets';
    }

    /**
     * @return list<string> Paths relative to the theme package root.
     */
    public function assetEntryPaths(): array
    {
        return $this->assetEntries;
    }

    /**
     * @return list<string> Paths relative to the application root (Vite inputs).
     */
    public function projectRelativeAssetEntries(): array
    {
        return ThemeAssetManifest::toProjectRelativeEntries($this->path, $this->assetEntries);
    }

    public function hasAssets(): bool
    {
        return $this->assetEntries !== [];
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
            'author' => $this->author,
            'description' => $this->description,
            'parent' => $this->parent,
            'views' => $this->viewsRelativePath !== 'resources/views' ? $this->viewsRelativePath : null,
            'assets' => $this->assetEntries !== [] ? ['entries' => $this->assetEntries] : null,
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
        $author = isset($data['author']) && is_string($data['author'])
            ? trim($data['author'])
            : null;
        $parent = isset($data['parent']) && is_string($data['parent'])
            ? trim($data['parent'])
            : null;
        $viewsRelativePath = isset($data['views']) && is_string($data['views'])
            ? trim(str_replace('\\', '/', $data['views']), '/')
            : 'resources/views';

        if ($description === '') {
            $description = null;
        }

        if ($author === '') {
            $author = null;
        }

        if ($parent === '') {
            $parent = null;
        }

        if ($parent !== null && strcasecmp($parent, $key) === 0) {
            throw InvalidThemeManifestException::selfParent($directoryName);
        }

        if ($viewsRelativePath === '') {
            throw InvalidThemeManifestException::invalidViewsPath($directoryName);
        }

        $assetEntries = ThemeAssetManifest::entriesFromManifest($data, $directory);

        return new self(
            key: $key,
            label: $label !== '' ? $label : $key,
            version: $version !== '' ? $version : '1.0.0',
            path: $directory,
            viewsRelativePath: $viewsRelativePath,
            description: $description,
            author: $author,
            parent: $parent,
            assetEntries: $assetEntries,
            raw: $data,
        );
    }
}
