<?php

namespace Tests\Feature\Services;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Core\Services\Services\ServiceConfigService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-services',
        ]);
    }

    public function test_admin_can_view_services_index_and_show(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['company_name' => 'Acme Hosting']);
        $service = Service::factory()->active()->forClient($client)->create([
            'hostname' => 'node-01.acme.test',
            'ip_address' => '203.0.113.10',
            'module' => 'pterodactyl',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('node-01.acme.test')
            ->assertSee('Acme Hosting');

        $this->actingAs($admin)
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee('node-01.acme.test')
            ->assertSee('203.0.113.10')
            ->assertSee('pterodactyl')
            ->assertSee('Acme Hosting');
    }

    public function test_support_can_view_but_cannot_manage_service_actions(): void
    {
        $support = User::factory()->withRole('support')->create();
        $service = Service::factory()->active()->create();

        $this->actingAs($support)
            ->get(route('admin.services.index'))
            ->assertOk();

        $this->actingAs($support)
            ->get(route('admin.services.show', $service))
            ->assertOk();

        $this->actingAs($support)
            ->post(route('admin.services.suspend', $service))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.services.terminate', $service))
            ->assertForbidden();
    }

    public function test_client_role_cannot_access_admin_services(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->actingAs($clientUser)
            ->get(route('admin.services.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_services(): void
    {
        $this->get(route('admin.services.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_suspend_unsuspend_and_terminate(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $service = Service::factory()->active()->create();

        $this->actingAs($admin)
            ->post(route('admin.services.suspend', $service))
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHas('status');

        $this->assertSame(ServiceStatus::Suspended, $service->fresh()->status);
        $this->assertSame(1, ServiceActionLog::query()->where('service_id', $service->id)->count());

        $this->actingAs($admin)
            ->post(route('admin.services.unsuspend', $service))
            ->assertRedirect(route('admin.services.show', $service));

        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.services.terminate', $service))
            ->assertRedirect(route('admin.services.show', $service));

        $this->assertSame(ServiceStatus::Terminated, $service->fresh()->status);
    }

    public function test_admin_restart_on_active_service(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $service = Service::factory()->active()->create();

        $this->actingAs($admin)
            ->post(route('admin.services.restart', $service))
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHas('status');

        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame(1, ServiceActionLog::query()->where('service_id', $service->id)->count());
    }

    public function test_disallowed_action_returns_validation_error(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $service = Service::factory()->suspended()->create();

        $this->actingAs($admin)
            ->from(route('admin.services.show', $service))
            ->post(route('admin.services.start', $service))
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHasErrors('action');

        $this->assertSame(ServiceStatus::Suspended, $service->fresh()->status);
    }

    public function test_show_renders_panel_link_when_available(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $service = Service::factory()->active()->create();

        app(ServiceConfigService::class)->set($service, [
            'access' => [
                'panel_url' => 'https://panel.example/server/42',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee('Open panel')
            ->assertSee('https://panel.example/server/42');
    }
}
