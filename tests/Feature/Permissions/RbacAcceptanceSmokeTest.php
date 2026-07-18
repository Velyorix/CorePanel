<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Permissions\Models\Role;
use Core\Permissions\Services\PermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * End-to-end smoke coverage for RBAC permissions and role assignment.
 */
class RbacAcceptanceSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.smoke',
            'corepanel.auth.email_verification.required' => false,
        ]);

        Route::middleware(['web', 'auth', 'permission:admin.access'])
            ->get('/rbac-smoke/admin', fn () => response('admin-ok'));
    }

    public function test_super_admin_has_total_access(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $targetUser = User::factory()->create();
        $client = Client::factory()->create();
        $role = Role::query()->where('name', 'client')->firstOrFail();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $targetUser));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $role));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('create', Role::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('impersonate', $client));
        $this->assertTrue(app(PermissionService::class)->userHasPermission($superAdmin, 'roles.manage'));

        $this->actingAs($superAdmin)
            ->get('/rbac-smoke/admin')
            ->assertOk()
            ->assertSee('admin-ok');
    }

    public function test_client_role_is_limited_to_owned_resources(): void
    {
        $clientUser = User::factory()->withRole('client')->create();
        $ownedClient = Client::factory()->create(['user_id' => $clientUser->id]);
        $foreignClient = Client::factory()->create();
        $permissionService = app(PermissionService::class);

        $this->assertTrue($permissionService->userCan($clientUser, 'clients.view', $ownedClient));
        $this->assertFalse($permissionService->userCan($clientUser, 'clients.view', $foreignClient));
        $this->assertFalse($permissionService->userHasPermission($clientUser, 'admin.access'));
        $this->assertFalse(Gate::forUser($clientUser)->allows('viewAny', Role::class));
    }

    public function test_missing_permission_returns_forbidden(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get('/rbac-smoke/admin')
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_role_modification_invalidates_permission_cache(): void
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
        $this->assertContains(
            'clients.view',
            $permissionService->permissionsForUser($user->fresh()),
        );
    }
}
