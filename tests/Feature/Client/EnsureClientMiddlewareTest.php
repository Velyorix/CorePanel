<?php

namespace Tests\Feature\Client;

use App\Http\Middleware\EnsureClient;
use App\Models\User;
use Core\Permissions\Services\UserPermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureClientMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.ensure-client',
            'corepanel.rbac.user_overrides.enabled' => true,
        ]);

        Route::middleware('client')
            ->get('/client-test/access', fn () => response('client-panel'))
            ->name('client.test.access');
    }

    public function test_client_user_can_access_client_middleware_route(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get('/client-test/access')
            ->assertOk()
            ->assertSee('client-panel');
    }

    public function test_admin_user_is_forbidden_from_client_middleware_route(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get('/client-test/access')
            ->assertForbidden()
            ->assertSee(__('You do not have access to the client area.'), false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/client-test/access')
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_json_request_receives_unauthorized(): void
    {
        $this->getJson('/client-test/access')
            ->assertUnauthorized();
    }

    public function test_forbidden_json_request_receives_forbidden_status(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->getJson('/client-test/access')
            ->assertForbidden();
    }

    public function test_user_with_granted_client_access_override_can_access_client_routes(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        app(UserPermissionService::class)->grant($admin, 'client.access');

        $this->actingAs($admin->fresh())
            ->get('/client-test/access')
            ->assertOk();
    }

    public function test_all_registered_client_routes_use_client_middleware_group(): void
    {
        $clientRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'client.'));

        $this->assertNotEmpty($clientRoutes);

        foreach ($clientRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertTrue(
                in_array('client', $middleware, true) || in_array(EnsureClient::class, $middleware, true),
                sprintf('Route [%s] must use the client middleware group.', $route->getName()),
            );
        }
    }
}
