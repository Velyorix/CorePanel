<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => false,
            'corepanel.auth.email_verification.required' => false,
        ]);

        Route::middleware(['web', 'auth', 'permission:admin.access'])
            ->get('/rbac-test/admin-access', fn () => response('admin-allowed'))
            ->name('rbac.test.admin-access');

        Route::middleware(['web', 'auth', 'permission:users.view,users.update'])
            ->get('/rbac-test/users-manage', fn () => response('users-manage-allowed'))
            ->name('rbac.test.users-manage');

        Route::middleware(['web', 'auth', 'role:admin,super-admin'])
            ->get('/rbac-test/admin-role', fn () => response('admin-role-allowed'))
            ->name('rbac.test.admin-role');

        Route::middleware(['web', 'auth', 'role:client'])
            ->get('/rbac-test/client-role', fn () => response('client-role-allowed'))
            ->name('rbac.test.client-role');

        Route::middleware(['web', 'permission:admin.access'])
            ->get('/rbac-test/permission-without-auth', fn () => response('allowed'))
            ->name('rbac.test.permission-without-auth');
    }

    public function test_user_with_required_permission_can_access_route(): void
    {
        $user = User::factory()->withRole('admin')->create();

        $this->actingAs($user)
            ->get('/rbac-test/admin-access')
            ->assertOk()
            ->assertSee('admin-allowed');
    }

    public function test_user_without_required_permission_receives_forbidden(): void
    {
        $user = User::factory()->withRole('client')->create();

        $this->actingAs($user)
            ->get('/rbac-test/admin-access')
            ->assertForbidden();
    }

    public function test_permission_middleware_requires_all_listed_permissions(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($admin)
            ->get('/rbac-test/users-manage')
            ->assertOk();

        $this->actingAs($support)
            ->get('/rbac-test/users-manage')
            ->assertForbidden();
    }

    public function test_role_middleware_allows_any_matching_role(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($admin)
            ->get('/rbac-test/admin-role')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get('/rbac-test/admin-role')
            ->assertOk();

        $this->actingAs($client)
            ->get('/rbac-test/client-role')
            ->assertOk();

        $this->actingAs($client)
            ->get('/rbac-test/admin-role')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/rbac-test/admin-access')
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_json_requests_are_rejected_by_permission_middleware(): void
    {
        $this->getJson('/rbac-test/permission-without-auth')
            ->assertUnauthorized();
    }

    public function test_json_requests_receive_forbidden_status_without_permission(): void
    {
        $user = User::factory()->withRole('client')->create();

        $this->actingAs($user)
            ->getJson('/rbac-test/admin-access')
            ->assertForbidden();
    }
}
