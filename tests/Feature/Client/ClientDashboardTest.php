<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-dashboard',
        ]);
    }

    public function test_client_can_view_dashboard_skeleton_with_kpi_placeholders(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee(__('Dashboard'), false)
            ->assertSee(__('Active services'), false)
            ->assertSee(__('Suspended services'), false)
            ->assertSee(__('Overall service status'), false)
            ->assertSee(__('Unpaid invoices'), false)
            ->assertSee(__('Available credits'), false)
            ->assertSee(__('Open tickets'), false)
            ->assertSee(__('Skeleton dashboard'), false)
            ->assertSee('data-kpi="services_active"', false)
            ->assertSee('data-kpi="invoices_unpaid"', false)
            ->assertSee('data-kpi="tickets_open"', false);
    }

    public function test_admin_without_client_access_cannot_view_client_dashboard(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_client_dashboard(): void
    {
        $this->get(route('client.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_is_highlighted_in_client_navigation(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee(route('client.dashboard'), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="'.__('Client menu').'"', false);
    }
}
