<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Permissions\Services\PermissionService;
use Core\Permissions\Services\RoleInheritanceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RoleInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private RoleInheritanceService $roleInheritanceService;

    private PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac',
            'corepanel.rbac.inheritance.enabled' => true,
        ]);

        $this->roleInheritanceService = app(RoleInheritanceService::class);
        $this->permissionService = app(PermissionService::class);
    }

    public function test_system_roles_are_linked_in_hierarchy(): void
    {
        $support = Role::query()->where('name', 'support')->firstOrFail();
        $admin = Role::query()->where('name', 'admin')->firstOrFail();
        $superAdmin = Role::query()->where('name', 'super-admin')->firstOrFail();

        $this->assertNull($support->parent_id);
        $this->assertSame($support->id, $admin->parent_id);
        $this->assertSame($admin->id, $superAdmin->parent_id);
    }

    public function test_higher_role_inherits_permissions_from_parent_roles(): void
    {
        $support = Role::query()->where('name', 'support')->firstOrFail();
        $admin = Role::query()->where('name', 'admin')->firstOrFail();

        $supportPermissions = $this->roleInheritanceService->permissionsForRole($support);
        $adminPermissions = $this->roleInheritanceService->permissionsForRole($admin);

        $this->assertContains('tickets.reply', $supportPermissions);
        $this->assertContains('tickets.reply', $adminPermissions);
        $this->assertContains('users.view', $adminPermissions);
        $this->assertNotContains('users.view', $supportPermissions);
    }

    public function test_user_with_inherited_role_receives_parent_permissions(): void
    {
        $manager = Role::query()->create([
            'name' => 'manager-test',
            'description' => 'Manager test role',
            'parent_id' => Role::query()->where('name', 'support')->value('id'),
        ]);

        $manager->permissions()->sync([
            Permission::query()->where('name', 'settings.view')->value('id'),
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([$manager->id]);

        $permissions = $this->permissionService->permissionsForUser($user);

        $this->assertContains('settings.view', $permissions);
        $this->assertContains('tickets.reply', $permissions);
        $this->assertContains('clients.view', $permissions);
        $this->assertNotContains('users.view', $permissions);
    }

    public function test_support_user_does_not_inherit_admin_permissions(): void
    {
        $supportUser = User::factory()->withRole('support')->create();

        $this->assertFalse($this->permissionService->userHasPermission($supportUser, 'users.view'));
        $this->assertTrue($this->permissionService->userHasPermission($supportUser, 'tickets.reply'));
    }

    public function test_role_inheritance_can_be_disabled_via_config(): void
    {
        config(['corepanel.rbac.inheritance.enabled' => false]);

        $parent = Role::query()->create([
            'name' => 'inheritance-parent-test',
            'description' => 'Parent role',
        ]);

        $parent->permissions()->sync([
            Permission::query()->where('name', 'tickets.view')->value('id'),
        ]);

        $child = Role::query()->create([
            'name' => 'inheritance-child-test',
            'description' => 'Child role',
            'parent_id' => $parent->id,
        ]);

        $child->permissions()->sync([
            Permission::query()->where('name', 'admin.access')->value('id'),
        ]);

        $permissions = $this->roleInheritanceService->permissionsForRole($child->fresh());

        $this->assertContains('admin.access', $permissions);
        $this->assertNotContains('tickets.view', $permissions);
    }

    public function test_forget_role_invalidates_users_with_descendant_roles(): void
    {
        $manager = Role::query()->create([
            'name' => 'cache-manager-test',
            'description' => 'Cache manager test role',
            'parent_id' => Role::query()->where('name', 'support')->value('id'),
        ]);

        $manager->permissions()->sync([
            Permission::query()->where('name', 'audit.view')->value('id'),
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([$manager->id]);

        $this->permissionService->permissionsForUser($user);

        $support = Role::query()->where('name', 'support')->firstOrFail();
        $support->permissions()->detach(
            Permission::query()->where('name', 'tickets.reply')->value('id'),
        );

        $this->permissionService->forgetRole($support);

        $this->assertFalse(
            $this->permissionService->userHasPermission($user->fresh(), 'tickets.reply'),
        );
    }

    public function test_would_create_cycle_detects_invalid_parent_assignment(): void
    {
        $support = Role::query()->where('name', 'support')->firstOrFail();
        $admin = Role::query()->where('name', 'admin')->firstOrFail();

        $this->assertTrue($this->roleInheritanceService->wouldCreateCycle($support, $admin->id));
        $this->assertFalse($this->roleInheritanceService->wouldCreateCycle($admin, $support->id));
    }
}
