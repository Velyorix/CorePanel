<?php

namespace Tests\Unit\Providers;

use Core\Providers\DataTransferObjects\ModulePermissionManifest;
use Core\Providers\Exceptions\InvalidModulePermissionException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ModulePermissionManifestTest extends TestCase
{
    public function test_builds_manifest_from_string_permissions(): void
    {
        $manifest = ModulePermissionManifest::fromArray('pterodactyl', [
            'permissions' => [
                'module.pterodactyl.server.create',
                'module.pterodactyl.server.restart',
            ],
        ]);

        $this->assertSame('pterodactyl', $manifest->moduleKey);
        $this->assertSame([
            'module.pterodactyl.server.create',
            'module.pterodactyl.server.restart',
        ], $manifest->permissionNames());
    }

    public function test_builds_manifest_from_object_permissions(): void
    {
        $manifest = ModulePermissionManifest::fromArray('cpanel', [
            'name' => 'cpanel',
            'permissions' => [
                [
                    'name' => 'module.cpanel.account.suspend',
                    'description' => 'Suspend hosting accounts',
                ],
            ],
        ]);

        $this->assertSame('cpanel', $manifest->moduleKey);
        $this->assertCount(1, $manifest->permissions);
        $this->assertSame('module.cpanel.account.suspend', $manifest->permissions[0]->name);
        $this->assertSame('Suspend hosting accounts', $manifest->permissions[0]->description);
    }

    public function test_prefers_manifest_name_over_directory_key(): void
    {
        $manifest = ModulePermissionManifest::fromArray('ignored-folder', [
            'name' => 'proxmox',
            'permissions' => [],
        ]);

        $this->assertSame('proxmox', $manifest->moduleKey);
    }

    public function test_rejects_invalid_manifest_permission_entry(): void
    {
        $this->expectException(InvalidModulePermissionException::class);

        ModulePermissionManifest::fromArray('pterodactyl', [
            'permissions' => [
                ['description' => 'Missing name field'],
            ],
        ]);
    }

    public function test_rejects_empty_module_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ModulePermissionManifest::fromArray('', [
            'permissions' => [],
        ]);
    }
}
