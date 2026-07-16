<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminMobileSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-mobile-sidebar',
        ]);
    }

    public function test_admin_layout_renders_mobile_sidebar_drawer_controls(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.admin title="Dashboard" page-heading="Dashboard">
                Page body
            </x-layout.admin>
        BLADE);

        $this->assertStringContainsString('data-admin-sidebar-toggle', $html);
        $this->assertStringContainsString('data-admin-sidebar-drawer', $html);
        $this->assertStringContainsString('data-admin-sidebar-overlay', $html);
        $this->assertStringContainsString('data-admin-sidebar-desktop', $html);
        $this->assertStringContainsString('data-admin-sidebar-close', $html);
        $this->assertStringContainsString('aria-label="'.__('Open admin menu').'"', $html);
        $this->assertStringContainsString('aria-label="'.__('Close admin menu').'"', $html);
        $this->assertStringContainsString('x-bind:aria-expanded="sidebarOpen"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="sidebarOpen = false"', $html);
        $this->assertStringContainsString('if ($event.target.closest(\'a[href]\')) sidebarOpen = false', $html);
    }

    public function test_admin_dashboard_includes_mobile_drawer_and_desktop_sidebar(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-sidebar-toggle', false)
            ->assertSee('data-admin-sidebar-drawer', false)
            ->assertSee('data-admin-sidebar-desktop', false)
            ->assertSee('aria-label="'.__('Admin menu').'"', false)
            ->assertSee('lg:hidden', false)
            ->assertSee('lg:flex', false);
    }

    public function test_custom_sidebar_slot_is_rendered_in_mobile_drawer(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.admin title="Custom">
                <x-slot:sidebar>
                    <nav aria-label="Custom admin navigation">Custom drawer nav</nav>
                </x-slot:sidebar>
                Body
            </x-layout.admin>
        BLADE);

        $this->assertSame(2, substr_count($html, 'Custom drawer nav'));
        $this->assertStringContainsString('data-admin-sidebar-drawer', $html);
    }
}
