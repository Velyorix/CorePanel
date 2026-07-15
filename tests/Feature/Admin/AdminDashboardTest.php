<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-dashboard',
        ]);
    }

    public function test_admin_can_view_dashboard_skeleton_with_kpi_placeholders(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Dashboard'), false)
            ->assertSee(__('Total revenue'), false)
            ->assertSee(__('Active clients'), false)
            ->assertSee(__('Active services'), false)
            ->assertSee(__('Renewal rate'), false)
            ->assertSee(__('Open tickets'), false)
            ->assertSee(__('System status'), false)
            ->assertSee(__('Node load'), false)
            ->assertSee(__('Critical alerts'), false)
            ->assertSee(__('Revenue by day'), false)
            ->assertSee(__('Skeleton dashboard'), false)
            ->assertSee('data-kpi="revenue"', false)
            ->assertSee('data-chart="revenue_daily"', false);
    }

    public function test_support_can_view_admin_dashboard(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Open tickets'), false);
    }

    public function test_client_cannot_view_admin_dashboard(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_is_highlighted_in_admin_navigation(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.dashboard'), false)
            ->assertSee('aria-current="page"', false);
    }
}
