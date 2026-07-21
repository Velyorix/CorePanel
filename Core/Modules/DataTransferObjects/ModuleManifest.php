<?php

namespace Core\Modules\DataTransferObjects;

use Core\Modules\Exceptions\InvalidModuleManifestException;
use Core\Providers\DataTransferObjects\ModulePermissionManifest;
use JsonException;

/**
 * Parsed module.json schema.
 *
 * Required: name, version, capabilities
 * Optional: label, description, module, providers, authors, homepage,
 *           license, requires, permissions
 */
final readonly class ModuleManifest
{
    /**
     * @param  list<string>  $capabilities
     * @param  list<string>  $providers
     * @param  list<ModuleAuthor>  $authors
     * @param  list<string|array{name?: string, description?: string|null}>  $permissions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $version,
        public string $path,
        public array $capabilities = [],
        public ?string $description = null,
        public ?string $moduleClass = null,
        public array $providers = [],
        public array $authors = [],
        public ?string $homepage = null,
        public ?string $license = null,
        public ModuleRequirements $requires = new ModuleRequirements,
        public array $permissions = [],
        public array $raw = [],
    ) {
    }

    public function manifestPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'module.json';
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function permissionManifest(): ModulePermissionManifest
    {
        return ModulePermissionManifest::fromArray($this->key, [
            'name' => $this->key,
            'permissions' => $this->permissions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->key,
            'version' => $this->version,
            'label' => $this->name !== $this->key ? $this->name : null,
            'description' => $this->description,
            'capabilities' => $this->capabilities,
            'module' => $this->moduleClass,
            'providers' => $this->providers === [] ? null : $this->providers,
            'authors' => $this->authors === [] ? null : array_map(
                static fn (ModuleAuthor $author): array => $author->toArray(),
                $this->authors,
            ),
            'homepage' => $this->homepage,
            'license' => $this->license,
            'requires' => $this->requires->isEmpty() ? null : $this->requires->toArray(),
            'permissions' => $this->permissions === [] ? null : $this->permissions,
        ], static fn (mixed $value): bool => $value !== null);
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

        if (! self::isValidVersion($version)) {
            throw InvalidModuleManifestException::invalidVersion($version);
        }

        if (! array_key_exists('capabilities', $data)) {
            throw InvalidModuleManifestException::missingField('capabilities');
        }

        $capabilities = self::normalizeCapabilities($data['capabilities']);
        $providers = self::normalizeClassList($data['providers'] ?? [], 'providers');
        $moduleClass = self::normalizeOptionalClass($data['module'] ?? null, 'module');
        $authors = self::normalizeAuthors($data['authors'] ?? []);
        $permissions = self::normalizePermissions($data['permissions'] ?? []);
        $requires = self::normalizeRequires($data['requires'] ?? null);

        $label = trim((string) ($data['label'] ?? $key));
        $description = isset($data['description']) ? trim((string) $data['description']) : null;
        $homepage = isset($data['homepage']) ? trim((string) $data['homepage']) : null;
        $license = isset($data['license']) ? trim((string) $data['license']) : null;

        return new self(
            key: $key,
            name: $label !== '' ? $label : $key,
            version: $version,
            path: rtrim($path, DIRECTORY_SEPARATOR.'/\\'),
            capabilities: $capabilities,
            description: $description !== '' ? $description : null,
            moduleClass: $moduleClass,
            providers: $providers,
            authors: $authors,
            homepage: $homepage !== '' ? $homepage : null,
            license: $license !== '' ? $license : null,
            requires: $requires,
            permissions: $permissions,
            raw: $data,
        );
    }

    private static function isValidVersion(string $version): bool
    {
        return (bool) preg_match(
            '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',
            $version,
        );
    }

    /**
     * @return list<string>
     */
    private static function normalizeCapabilities(mixed $capabilities): array
    {
        if (! is_array($capabilities)) {
            throw InvalidModuleManifestException::invalidCapabilities();
        }

        $normalized = [];

        foreach ($capabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                throw InvalidModuleManifestException::invalidCapabilities();
            }

            $normalized[] = trim($capability);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<string>
     */
    private static function normalizeClassList(mixed $classes, string $field): array
    {
        if ($classes === null) {
            return [];
        }

        if (! is_array($classes)) {
            throw InvalidModuleManifestException::invalidField($field, 'must be a list of class names.');
        }

        $normalized = [];

        foreach ($classes as $class) {
            if (! is_string($class) || trim($class) === '') {
                throw InvalidModuleManifestException::invalidField($field, 'must be a list of class names.');
            }

            $normalized[] = ltrim(trim($class), '\\');
        }

        return array_values(array_unique($normalized));
    }

    private static function normalizeOptionalClass(mixed $class, string $field): ?string
    {
        if ($class === null || $class === '') {
            return null;
        }

        if (! is_string($class)) {
            throw InvalidModuleManifestException::invalidField($field, 'must be a class name string.');
        }

        $normalized = ltrim(trim($class), '\\');

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return list<ModuleAuthor>
     */
    private static function normalizeAuthors(mixed $authors): array
    {
        if ($authors === null) {
            return [];
        }

        if (! is_array($authors)) {
            throw InvalidModuleManifestException::invalidField('authors', 'must be a list.');
        }

        $normalized = [];

        foreach ($authors as $author) {
            if (! is_string($author) && ! is_array($author)) {
                throw InvalidModuleManifestException::invalidField('authors', 'entries must be strings or objects.');
            }

            $normalized[] = ModuleAuthor::fromManifestEntry($author);
        }

        return $normalized;
    }

    /**
     * @return list<string|array{name: string, description?: string|null}>
     */
    private static function normalizePermissions(mixed $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        if (! is_array($permissions)) {
            throw InvalidModuleManifestException::invalidField('permissions', 'must be a list.');
        }

        $normalized = [];

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $name = trim($permission);

                if ($name === '') {
                    throw InvalidModuleManifestException::invalidField('permissions', 'entries cannot be empty.');
                }

                $normalized[] = $name;

                continue;
            }

            if (! is_array($permission) || ! isset($permission['name']) || ! is_string($permission['name'])) {
                throw InvalidModuleManifestException::invalidField(
                    'permissions',
                    'entries must be strings or objects with a name.',
                );
            }

            $name = trim($permission['name']);

            if ($name === '') {
                throw InvalidModuleManifestException::invalidField('permissions', 'entries cannot be empty.');
            }

            $entry = ['name' => $name];

            if (array_key_exists('description', $permission)) {
                $description = $permission['description'];
                $entry['description'] = is_string($description) ? $description : null;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    private static function normalizeRequires(mixed $requires): ModuleRequirements
    {
        if ($requires === null) {
            return new ModuleRequirements;
        }

        if (! is_array($requires)) {
            throw InvalidModuleManifestException::invalidField('requires', 'must be an object.');
        }

        /** @var array{corepanel?: mixed, php?: mixed} $requires */
        return ModuleRequirements::fromArray($requires);
    }
}
