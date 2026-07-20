<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Permissions\Services\PermissionRegistry;
use Core\Permissions\Services\PermissionService;
use Core\Providers\DataTransferObjects\ModulePermissionDefinition;
use Core\Providers\DataTransferObjects\ModulePermissionManifest;
use Core\Providers\Exceptions\InvalidModulePermissionException;
use Core\Providers\Services\ModulePermissionRegistrar;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ModulePermissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => false,
            'corepanel.rbac.permissions_registry.cache_enabled' => false,
        ]);
    }

    public function test_registers_module_permissions_in_database_and_registry(): void
    {
        $registrar = app(ModulePermissionRegistrar::class);
        $registry = app(PermissionRegistry::class);

        $registrar->register(new ModulePermissionManifest('pterodactyl', [
            ModulePermissionDefinition::fromManifestEntry('module.pterodactyl.server.create'),
            ModulePermissionDefinition::fromManifestEntry([
                'name' => 'module.pterodactyl.server.restart',
                'description' => 'Restart game servers',
            ]),
        ]));

        $this->assertDatabaseHas('permissions', [
            'name' => 'module.pterodactyl.server.create',
            'module' => 'pterodactyl',
        ]);
        $this->assertDatabaseHas('permissions', [
            'name' => 'module.pterodactyl.server.restart',
            'module' => 'pterodactyl',
            'description' => 'Restart game servers',
        ]);

        $this->assertTrue($registry->contains('module.pterodactyl.server.create'));
        $this->assertTrue($registry->contains('module.pterodactyl.server.restart'));
    }

    public function test_registered_module_permissions_are_authorizable_via_gate(): void
    {
        $registrar = app(ModulePermissionRegistrar::class);
        $admin = User::factory()->withRole('admin')->create();

        $registrar->register(ModulePermissionManifest::fromArray('pterodactyl', [
            'permissions' => ['module.pterodactyl.server.create'],
        ]));

        $permission = Permission::query()->where('name', 'module.pterodactyl.server.create')->firstOrFail();
        Role::query()->where('name', 'admin')->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);

        app(PermissionService::class)->forgetAll();

        $this->assertTrue(Gate::forUser($admin)->allows('module.pterodactyl.server.create'));
        $this->assertFalse(Gate::forUser(User::factory()->withRole('client')->create())->allows('module.pterodactyl.server.create'));
    }

    public function test_rejects_permissions_outside_module_namespace(): void
    {
        $registrar = app(ModulePermissionRegistrar::class);

        $this->expectException(InvalidModulePermissionException::class);
        $this->expectExceptionMessage('must start with [module.]');

        $registrar->register(ModulePermissionManifest::fromArray('pterodactyl', [
            'permissions' => ['pterodactyl.server.create'],
        ]));
    }

    public function test_rejects_permissions_with_mismatched_module_key(): void
    {
        $registrar = app(ModulePermissionRegistrar::class);

        $this->expectException(InvalidModulePermissionException::class);
        $this->expectExceptionMessage('does not belong to module [pterodactyl]');

        $registrar->register(ModulePermissionManifest::fromArray('pterodactyl', [
            'permissions' => ['module.proxmox.vm.restart'],
        ]));
    }

    public function test_unregister_removes_module_permissions_and_invalidates_registry(): void
    {
        $registrar = app(ModulePermissionRegistrar::class);
        $registry = app(PermissionRegistry::class);

        $registrar->register(ModulePermissionManifest::fromArray('pterodactyl', [
            'permissions' => [
                'module.pterodactyl.server.create',
                'module.pterodactyl.server.restart',
            ],
        ]));

        $deleted = $registrar->unregister('pterodactyl');

        $this->assertSame(2, $deleted);
        $this->assertDatabaseMissing('permissions', ['name' => 'module.pterodactyl.server.create']);
        $this->assertFalse($registry->contains('module.pterodactyl.server.create'));
    }

    public function test_scan_registers_permissions_from_module_json_files(): void
    {
        $modulesPath = storage_path('framework/testing/modules-'.uniqid('', true));
        $moduleDirectory = $modulesPath.'/pterodactyl';
        mkdir($moduleDirectory, 0777, true);

        file_put_contents($moduleDirectory.'/module.json', json_encode([
            'name' => 'pterodactyl',
            'permissions' => [
                'module.pterodactyl.server.create',
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $registered = app(ModulePermissionRegistrar::class)->scan($modulesPath);

            $this->assertSame(1, $registered);
            $this->assertDatabaseHas('permissions', [
                'name' => 'module.pterodactyl.server.create',
                'module' => 'pterodactyl',
            ]);
        } finally {
            @unlink($moduleDirectory.'/module.json');
            @rmdir($moduleDirectory);
            @rmdir($modulesPath);
        }
    }

    public function test_permission_name_helper_builds_expected_format(): void
    {
        $this->assertSame(
            'module.pterodactyl.server.create',
            ModulePermissionRegistrar::permissionName('pterodactyl', 'server', 'create'),
        );
    }
}
