<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Permissions\Services\PermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac',
        ]);
    }

    public function test_super_admin_can_view_roles_and_permissions_pages(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('super-admin');

        $this->actingAs($superAdmin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertSee('roles.manage');
    }

    public function test_admin_can_view_roles_but_cannot_create(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.roles.create'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'ops',
                'description' => 'Ops role',
            ])
            ->assertForbidden();
    }

    public function test_support_user_cannot_access_role_management(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_roles(): void
    {
        $this->get(route('admin.roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_create_update_duplicate_and_delete_custom_role(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $assignee = User::factory()->create();
        $parent = Role::query()->where('name', 'support')->firstOrFail();
        $permissionIds = Permission::query()
            ->whereIn('name', ['tickets.view', 'clients.view'])
            ->pluck('id')
            ->all();

        $this->actingAs($superAdmin)
            ->post(route('admin.roles.store'), [
                'name' => 'ops-lead',
                'description' => 'Operations lead',
                'parent_id' => $parent->id,
                'permission_ids' => $permissionIds,
                'user_ids' => [$assignee->id],
            ])
            ->assertRedirect();

        $role = Role::query()->where('name', 'ops-lead')->firstOrFail();

        $this->assertSame($parent->id, $role->parent_id);
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->users->contains('id', $assignee->id));
        $this->assertTrue(
            app(PermissionService::class)->userHasPermission($assignee->fresh(), 'tickets.view'),
        );

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'ops-lead',
                'description' => 'Updated ops lead',
                'parent_id' => $parent->id,
                'permission_ids' => $permissionIds,
                'user_ids' => [],
            ])
            ->assertRedirect(route('admin.roles.show', $role));

        $this->assertSame('Updated ops lead', $role->fresh()->description);
        $this->assertFalse(
            app(PermissionService::class)->userHasPermission($assignee->fresh(), 'tickets.view'),
        );

        $this->actingAs($superAdmin)
            ->post(route('admin.roles.duplicate', $role), [
                'name' => 'ops-lead-copy',
            ])
            ->assertRedirect();

        $duplicate = Role::query()->where('name', 'ops-lead-copy')->firstOrFail();
        $this->assertSame($role->permissions()->count(), $duplicate->permissions()->count());
        $this->assertCount(0, $duplicate->users);

        $this->actingAs($superAdmin)
            ->delete(route('admin.roles.destroy', $duplicate))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $duplicate->id]);
    }

    public function test_system_roles_cannot_be_deleted_or_renamed(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $systemRole = Role::query()->where('name', 'support')->firstOrFail();
        $permissionIds = $systemRole->permissions()->pluck('permissions.id')->all();

        $this->actingAs($superAdmin)
            ->delete(route('admin.roles.destroy', $systemRole))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $systemRole), [
                'name' => 'support-renamed',
                'description' => $systemRole->description,
                'parent_id' => $systemRole->parent_id,
                'permission_ids' => $permissionIds,
                'user_ids' => [],
            ])
            ->assertRedirect(route('admin.roles.show', $systemRole));

        $this->assertSame('support', $systemRole->fresh()->name);
    }

    public function test_updating_role_permissions_invalidates_user_cache(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $user = User::factory()->withRole('support')->create();
        $support = Role::query()->where('name', 'support')->firstOrFail();
        $permissionService = app(PermissionService::class);

        $this->assertTrue($permissionService->userHasPermission($user, 'tickets.reply'));

        $permissionIds = $support->permissions()
            ->where('name', '!=', 'tickets.reply')
            ->pluck('permissions.id')
            ->all();

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $support), [
                'name' => 'support',
                'description' => $support->description,
                'parent_id' => $support->parent_id,
                'permission_ids' => $permissionIds,
                'user_ids' => $support->users()->pluck('users.id')->all(),
            ])
            ->assertRedirect(route('admin.roles.show', $support));

        $this->assertFalse($permissionService->userHasPermission($user->fresh(), 'tickets.reply'));
    }

    public function test_cannot_create_inheritance_cycle_via_parent_assignment(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $support = Role::query()->where('name', 'support')->firstOrFail();
        $admin = Role::query()->where('name', 'admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $support), [
                'name' => 'support',
                'description' => $support->description,
                'parent_id' => $admin->id,
                'permission_ids' => $support->permissions()->pluck('permissions.id')->all(),
                'user_ids' => [],
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($support->fresh()->parent_id);
    }
}
