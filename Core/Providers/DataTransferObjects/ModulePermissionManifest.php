<?php

namespace Core\Providers\DataTransferObjects;

use Core\Providers\Exceptions\InvalidModulePermissionException;
use InvalidArgumentException;
use JsonException;

final readonly class ModulePermissionManifest
{
    /**
     * @param  list<ModulePermissionDefinition>  $permissions
     */
    public function __construct(
        public string $moduleKey,
        public array $permissions = [],
    ) {
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     permissions?: list<string|array{name?: string, description?: string|null}>
     * }  $data
     */
    public static function fromArray(string $moduleKey, array $data): self
    {
        $resolvedModuleKey = self::resolveModuleKey($moduleKey, $data);

        $permissions = [];

        foreach ($data['permissions'] ?? [] as $entry) {
            $permissions[] = ModulePermissionDefinition::fromManifestEntry($entry);
        }

        return new self($resolvedModuleKey, $permissions);
    }

    public static function fromJsonFile(string $path, ?string $moduleKey = null): self
    {
        if (! is_file($path)) {
            throw new JsonException("Module manifest not found at [{$path}].");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new JsonException("Unable to read module manifest at [{$path}].");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return self::fromArray($moduleKey ?? basename(dirname($path)), $data);
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_map(
            fn (ModulePermissionDefinition $permission): string => $permission->name,
            $this->permissions,
        );
    }

    /**
     * @param  array{name?: string|null}  $data
     */
    private static function resolveModuleKey(string $moduleKey, array $data): string
    {
        $resolved = trim((string) ($data['name'] ?? $moduleKey));

        if ($resolved === '') {
            throw new InvalidArgumentException('Module manifest must define a module key.');
        }

        return $resolved;
    }
}
