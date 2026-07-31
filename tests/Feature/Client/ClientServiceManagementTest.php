<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Permissions\Services\UserPermissionService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Core\Services\Services\ServiceConfigService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private UserPermissionService $userPermissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-services',
            'corepanel.rbac.user_overrides.enabled' => true,
        ]);

        $this->userPermissionService = app(UserPermissionService::class);
    }

    public function test_client_can_view_services_index_and_show(): void
    {
        [$user, $client] = $this->makeClientUser();
        $service = Service::factory()->active()->forClient($client)->create([
            'hostname' => 'game-01.client.test',
            'ip_address' => '198.51.100.20',
            'module' => 'pterodactyl',
        ]);

        $this->actingAs($user)
            ->get(route('client.services.index'))
            ->assertOk()
            ->assertSee('game-01.client.test')
            ->assertSee('pterodactyl');

        $this->actingAs($user)
            ->get(route('client.services.show', $service))
            ->assertOk()
            ->assertSee('game-01.client.test')
            ->assertSee('198.51.100.20')
            ->assertSee('pterodactyl');
    }

    public function test_client_cannot_view_another_clients_service(): void
    {
        [$user] = $this->makeClientUser();
        $otherService = Service::factory()->active()->create();

        $this->actingAs($user)
            ->get(route('client.services.show', $otherService))
            ->assertNotFound();
    }

    public function test_client_can_restart_active_service(): void
    {
        [$user, $client] = $this->makeClientUser();
        $service = Service::factory()->active()->forClient($client)->create();

        $this->actingAs($user)
            ->post(route('client.services.restart', $service))
            ->assertRedirect(route('client.services.show', $service))
            ->assertSessionHas('status');

        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame(1, ServiceActionLog::query()->where('service_id', $service->id)->count());
    }

    public function test_view_only_client_cannot_run_service_actions(): void
    {
        [$user, $client] = $this->makeClientUser();
        $this->userPermissionService->deny($user, 'client.services.manage');

        $service = Service::factory()->active()->forClient($client)->create();

        $this->actingAs($user)
            ->get(route('client.services.show', $service))
            ->assertOk()
            ->assertDontSee(route('client.services.restart', $service), false);

        $this->actingAs($user)
            ->post(route('client.services.restart', $service))
            ->assertForbidden();
    }

    public function test_disallowed_action_returns_validation_error(): void
    {
        [$user, $client] = $this->makeClientUser();
        $service = Service::factory()->suspended()->forClient($client)->create();

        $this->actingAs($user)
            ->from(route('client.services.show', $service))
            ->post(route('client.services.start', $service))
            ->assertRedirect(route('client.services.show', $service))
            ->assertSessionHasErrors('action');

        $this->assertSame(ServiceStatus::Suspended, $service->fresh()->status);
    }

    public function test_empty_services_index_shows_empty_state(): void
    {
        [$user] = $this->makeClientUser();

        $this->actingAs($user)
            ->get(route('client.services.index'))
            ->assertOk()
            ->assertSee(__('No services yet'));
    }

    public function test_guest_is_redirected_from_client_services(): void
    {
        $this->get(route('client.services.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_without_client_access_cannot_view_services(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.services.index'))
            ->assertForbidden();
    }

    public function test_services_nav_is_active_on_services_page(): void
    {
        [$user] = $this->makeClientUser();

        $this->actingAs($user)
            ->get(route('client.services.index'))
            ->assertOk()
            ->assertSee(__('Services'), false)
            ->assertSee(route('client.services.index'), false);
    }

    public function test_show_renders_panel_link_when_available(): void
    {
        [$user, $client] = $this->makeClientUser();
        $service = Service::factory()->active()->forClient($client)->create();

        app(ServiceConfigService::class)->set($service, [
            'access' => [
                'panel_url' => 'https://panel.example/server/99',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('client.services.show', $service))
            ->assertOk()
            ->assertSee(__('Open panel'))
            ->assertSee('https://panel.example/server/99', false);
    }

    /**
     * @param  array<string, mixed>  $clientAttributes
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(array $clientAttributes = []): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            ...$clientAttributes,
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }
}
