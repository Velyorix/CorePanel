<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Permissions\Enums\PermissionOverrideEffect;
use Core\Permissions\Models\Permission;
use Core\Permissions\Services\PermissionService;
use Core\Permissions\Services\UserPermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UserPermissionOverrideTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissionService;

    private UserPermissionService $userPermissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac',
            'corepanel.rbac.user_overrides.enabled' => true,
        ]);

        $this->permissionService = app(PermissionService::class);
        $this->userPermissionService = app(UserPermissionService::class);
    }

    public function test_grant_adds_permission_not_provided_by_role(): void
    {
        $user = User::factory()->withRole('support')->create();

        $this->assertFalse($this->permissionService->userHasPermission($user, 'users.view'));

        $this->userPermissionService->grant($user, 'users.view');

        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'users.view'));
        $this->assertContains('users.view', $this->userPermissionService->grantsForUser($user->fresh()));
    }

    public function test_deny_removes_permission_inherited_from_role(): void
    {
        $user = User::factory()->withRole('support')->create();

        $this->assertTrue($this->permissionService->userHasPermission($user, 'tickets.reply'));

        $this->userPermissionService->deny($user, 'tickets.reply');

        $this->assertFalse($this->permissionService->userHasPermission($user->fresh(), 'tickets.reply'));
        $this->assertContains('tickets.reply', $this->userPermissionService->deniesForUser($user->fresh()));
    }

    public function test_deny_wins_over_role_and_direct_grant_for_same_permission(): void
    {
        $user = User::factory()->withRole('support')->create();

        $this->userPermissionService->grant($user, 'tickets.reply');
        $this->userPermissionService->deny($user, 'tickets.reply');

        $this->assertFalse($this->permissionService->userHasPermission($user->fresh(), 'tickets.reply'));
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'permission_id' => Permission::query()->where('name', 'tickets.reply')->value('id'),
            'effect' => PermissionOverrideEffect::Deny->value,
        ]);
    }

    public function test_revoke_removes_override_and_restores_role_permissions(): void
    {
        $user = User::factory()->withRole('support')->create();

        $this->userPermissionService->deny($user, 'tickets.reply');
        $this->assertFalse($this->permissionService->userHasPermission($user->fresh(), 'tickets.reply'));

        $this->userPermissionService->revoke($user, 'tickets.reply');

        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'tickets.reply'));
        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $user->id,
            'permission_id' => Permission::query()->where('name', 'tickets.reply')->value('id'),
        ]);
    }

    public function test_user_overrides_can_be_disabled_via_config(): void
    {
        config(['corepanel.rbac.user_overrides.enabled' => false]);

        $user = User::factory()->withRole('support')->create();

        $this->userPermissionService->grant($user, 'users.view');
        $this->userPermissionService->deny($user, 'tickets.reply');

        $this->assertFalse($this->permissionService->userHasPermission($user->fresh(), 'users.view'));
        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'tickets.reply'));
        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_grant_invalidates_permission_cache(): void
    {
        $user = User::factory()->withRole('support')->create();

        $this->permissionService->permissionsForUser($user);

        $this->userPermissionService->grant($user, 'audit.view');

        $this->assertTrue($this->permissionService->userHasPermission($user->fresh(), 'audit.view'));
    }

    public function test_grant_rejects_unknown_permission_name(): void
    {
        $user = User::factory()->withRole('support')->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown permission [not.a.real.permission].');

        $this->userPermissionService->grant($user, 'not.a.real.permission');
    }
}
