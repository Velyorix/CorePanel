<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Permissions\Services\PermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac',
            'corepanel.rbac.cache.ttl_seconds' => 3600,
        ]);

        $this->permissionService = app(PermissionService::class);
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::factory()->withRole('super-admin')->create();

        $permissions = $this->permissionService->permissionsForUser($user);

        $this->assertCount(Permission::query()->count(), $permissions);
        $this->assertContains('admin.access', $permissions);
        $this->assertContains('client.account.manage', $permissions);
    }

    public function test_client_role_has_limited_permissions(): void
    {
        $user = User::factory()->withRole('client')->create();

        $permissions = $this->permissionService->permissionsForUser($user);

        $this->assertContains('client.access', $permissions);
        $this->assertContains('client.tickets.create', $permissions);
        $this->assertNotContains('admin.access', $permissions);
        $this->assertNotContains('users.delete', $permissions);
    }

    public function test_user_has_permission_and_role_checks(): void
    {
        $user = User::factory()->withRole('support')->create();

        $this->assertTrue($this->permissionService->userHasRole($user, 'support'));
        $this->assertFalse($this->permissionService->userHasRole($user, 'admin'));
        $this->assertTrue($this->permissionService->userHasPermission($user, 'tickets.reply'));
        $this->assertFalse($this->permissionService->userHasPermission($user, 'users.delete'));
        $this->assertTrue($this->permissionService->userHasAnyPermission($user, ['users.delete', 'tickets.reply']));
        $this->assertFalse($this->permissionService->userHasAllPermissions($user, ['tickets.reply', 'users.delete']));
        $this->assertTrue($this->permissionService->userHasAnyRole($user, ['admin', 'support']));
    }

    public function test_permissions_are_cached_for_subsequent_requests(): void
    {
        $user = User::factory()->withRole('client')->create();

        DB::enableQueryLog();

        $this->permissionService->permissionsForUser($user);
        $firstQueryCount = count(DB::getQueryLog());

        DB::flushQueryLog();

        $this->permissionService->permissionsForUser($user);
        $secondQueryCount = count(DB::getQueryLog());

        $this->assertGreaterThan(0, $firstQueryCount);
        $this->assertSame(0, $secondQueryCount);
    }

    public function test_assigning_role_invalidates_cached_permissions_automatically(): void
    {
        $user = User::factory()->withRole('client')->create();

        $this->permissionService->permissionsForUser($user);
        $this->assertFalse($this->permissionService->userHasPermission($user, 'admin.access'));

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'admin.access'));
    }

    public function test_forget_user_reloads_permissions_from_database(): void
    {
        $user = User::factory()->withRole('admin')->create();

        $this->assertTrue($this->permissionService->userHasPermission($user, 'admin.access'));

        $this->permissionService->forgetUser($user);

        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'admin.access'));
    }

    public function test_forget_role_invalidates_cache_for_all_assigned_users(): void
    {
        $role = Role::query()->where('name', 'support')->firstOrFail();
        $firstUser = User::factory()->withRole('support')->create();
        $secondUser = User::factory()->withRole('support')->create();

        $this->permissionService->permissionsForUser($firstUser);
        $this->permissionService->permissionsForUser($secondUser);

        $role->permissions()->detach(
            Permission::query()->where('name', 'tickets.reply')->value('id'),
        );

        $this->permissionService->forgetRole($role);

        $this->assertFalse($this->permissionService->userHasPermission($firstUser->fresh(), 'tickets.reply'));
        $this->assertFalse($this->permissionService->userHasPermission($secondUser->fresh(), 'tickets.reply'));
    }

    public function test_cache_can_be_disabled_via_config(): void
    {
        config(['corepanel.rbac.cache.enabled' => false]);

        $user = User::factory()->withRole('client')->create();
        $cacheKey = 'test.rbac.user.'.$user->id;

        $permissions = $this->permissionService->permissionsForUser($user);

        $this->assertContains('client.access', $permissions);
        $this->assertFalse(Cache::store('array')->has($cacheKey));
    }

    public function test_user_with_multiple_roles_receives_union_of_permissions(): void
    {
        $user = User::factory()->withRole('support')->create();
        $auditPermissionId = Permission::query()->where('name', 'audit.view')->value('id');

        $extraRole = Role::query()->create([
            'name' => 'auditor-extra',
            'description' => 'Extra auditor role',
            'is_system' => false,
        ]);
        $extraRole->permissions()->sync([$auditPermissionId]);
        $user->roles()->syncWithoutDetaching([$extraRole->id]);

        $this->permissionService->forgetUser($user);

        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'tickets.reply'));
        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'audit.view'));
        $this->assertFalse($this->permissionService->userHasPermission($user->fresh(), 'users.delete'));
    }

    public function test_user_without_roles_is_denied_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $this->permissionService->permissionsForUser($user));
        $this->assertSame([], $this->permissionService->rolesForUser($user));
        $this->assertFalse($this->permissionService->userHasPermission($user, 'admin.access'));
        $this->assertFalse($this->permissionService->userHasPermission($user, 'client.access'));
        $this->assertFalse($this->permissionService->userHasAnyRole($user, ['admin', 'client', 'support']));
    }
}
