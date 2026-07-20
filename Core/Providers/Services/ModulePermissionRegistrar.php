<?php

namespace Core\Providers\Services;

use Core\Permissions\Models\Permission;
use Core\Permissions\Services\PermissionRegistry;
use Core\Permissions\Services\PermissionService;
use Core\Providers\DataTransferObjects\ModulePermissionManifest;
use Core\Providers\Exceptions\InvalidModulePermissionException;
use Illuminate\Support\Facades\DB;
use JsonException;

class ModulePermissionRegistrar
{
    public function __construct(
        private readonly PermissionRegistry $permissionRegistry,
        private readonly PermissionService $permissionService,
    ) {
    }

    public function register(ModulePermissionManifest $manifest): void
    {
        DB::transaction(function () use ($manifest): void {
            foreach ($manifest->permissions as $definition) {
                $this->assertValidPermission($manifest->moduleKey, $definition->name);

                Permission::query()->updateOrCreate(
                    ['name' => $definition->name],
                    [
                        'module' => $manifest->moduleKey,
                        'description' => $definition->descriptionOrDefault($manifest->moduleKey),
                        'created_at' => now(),
                    ],
                );
            }
        });

        $this->invalidateCaches();
    }

    public function registerFromManifestFile(string $path, ?string $moduleKey = null): ModulePermissionManifest
    {
        $manifest = ModulePermissionManifest::fromJsonFile($path, $moduleKey);
        $this->register($manifest);

        return $manifest;
    }

    /**
     * Discover module.json manifests under the modules directory and register permissions.
     */
    public function scan(?string $modulesPath = null): int
    {
        $root = $modulesPath ?? base_path('Modules');

        if (! is_dir($root)) {
            return 0;
        }

        $registered = 0;

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $directory = $root.DIRECTORY_SEPARATOR.$entry;

            if (! is_dir($directory)) {
                continue;
            }

            $manifestPath = $directory.DIRECTORY_SEPARATOR.'module.json';

            if (! is_file($manifestPath)) {
                continue;
            }

            try {
                $this->registerFromManifestFile($manifestPath, $entry);
                $registered++;
            } catch (JsonException|InvalidModulePermissionException) {
                continue;
            }
        }

        return $registered;
    }

    public function unregister(string $moduleKey): int
    {
        $deleted = Permission::query()
            ->where('module', $moduleKey)
            ->where('name', 'like', 'module.'.$moduleKey.'.%')
            ->delete();

        if ($deleted > 0) {
            $this->invalidateCaches();
        }

        return $deleted;
    }

    public static function permissionName(string $moduleKey, string $resource, string $action): string
    {
        return 'module.'.$moduleKey.'.'.$resource.'.'.$action;
    }

    private function assertValidPermission(string $moduleKey, string $permission): void
    {
        if (! str_starts_with($permission, 'module.')) {
            throw InvalidModulePermissionException::notModuleScoped($permission);
        }

        $segments = explode('.', $permission);

        if (count($segments) < 4) {
            throw InvalidModulePermissionException::invalidFormat($permission);
        }

        if ($segments[1] !== $moduleKey) {
            throw InvalidModulePermissionException::moduleKeyMismatch($permission, $moduleKey);
        }
    }

    private function invalidateCaches(): void
    {
        $this->permissionRegistry->forget();
        $this->permissionService->forgetAll();
    }
}
