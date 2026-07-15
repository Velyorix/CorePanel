<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureAdmin;
use App\Models\User;
use Core\Permissions\Models\Permission;
use Core\Permissions\Services\UserPermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.ensure-admin',
            'corepanel.rbac.user_overrides.enabled' => true,
        ]);

        Route::middleware('admin')
            ->get('/admin-test/access', fn () => response('admin-panel'))
            ->name('admin.test.access');
    }

    public function test_admin_user_can_access_admin_middleware_route(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get('/admin-test/access')
            ->assertOk()
            ->assertSee('admin-panel');
    }

    public function test_support_user_can_access_admin_middleware_route(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get('/admin-test/access')
            ->assertOk();
    }

    public function test_client_user_is_forbidden_from_admin_middleware_route(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get('/admin-test/access')
            ->assertForbidden()
            ->assertSee(__('You do not have access to the admin panel.'), false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin-test/access')
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_json_request_receives_unauthorized(): void
    {
        $this->getJson('/admin-test/access')
            ->assertUnauthorized();
    }

    public function test_forbidden_json_request_receives_forbidden_status(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->getJson('/admin-test/access')
            ->assertForbidden();
    }

    public function test_user_with_granted_admin_access_override_can_access_admin_routes(): void
    {
        $client = User::factory()->withRole('client')->create();

        app(UserPermissionService::class)->grant($client, 'admin.access');

        $this->actingAs($client->fresh())
            ->get('/admin-test/access')
            ->assertOk();
    }

    public function test_all_registered_admin_routes_use_admin_middleware_group(): void
    {
        $adminRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.'));

        $this->assertNotEmpty($adminRoutes);

        foreach ($adminRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertTrue(
                in_array('admin', $middleware, true) || in_array(EnsureAdmin::class, $middleware, true),
                sprintf('Route [%s] must use the admin middleware group.', $route->getName()),
            );
        }
    }
}
