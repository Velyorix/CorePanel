<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-layout',
        ]);
    }

    public function test_admin_layout_renders_shell_regions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layout.admin title="Roles" page-heading="Roles">
                <x-slot:subtitle>Manage roles</x-slot:subtitle>
                <x-slot:sidebar>
                    <nav aria-label="Admin navigation">Sidebar nav</nav>
                </x-slot:sidebar>
                <x-slot:topbar>Top actions</x-slot:topbar>
                <x-slot:breadcrumbs>
                    <x-ui.breadcrumb :items="[
                        ['label' => 'Admin', 'url' => '/admin'],
                        ['label' => 'Roles'],
                    ]" />
                </x-slot:breadcrumbs>
                Page body
            </x-layout.admin>
        BLADE);

        $this->assertStringContainsString('Admin navigation', $html);
        $this->assertStringContainsString('Admin top bar', $html);
        $this->assertStringContainsString('Sidebar nav', $html);
        $this->assertStringContainsString('Top actions', $html);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Roles', $html);
        $this->assertStringContainsString('Manage roles', $html);
        $this->assertStringContainsString('Page body', $html);
        $this->assertStringContainsString('w-72', $html);
    }

    public function test_breadcrumb_marks_last_item_as_current(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.breadcrumb :items="[
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Roles', 'url' => '/admin/roles'],
                ['label' => 'Edit'],
            ]" />
        BLADE);

        $this->assertStringContainsString('href="/admin"', $html);
        $this->assertStringContainsString('href="/admin/roles"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Edit', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*"[^>]*>Edit</', $html);
    }

    public function test_roles_index_uses_admin_layout(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Admin navigation', false)
            ->assertSee('Admin top bar', false)
            ->assertSee('aria-label="'.__('Admin menu').'"', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('super-admin', false)
            ->assertSee(__('Roles'), false);
    }
}
