<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Core\Admin\Navigation\AdminNavigation;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-nav',
        ]);
    }

    public function test_navigation_includes_tome6_structure_and_wired_rbac_routes(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $sections = app(AdminNavigation::class)->forUser($admin);

        $labels = collect($sections)
            ->flatMap(function (array $section): array {
                $sectionLabels = filled($section['label']) ? [$section['label']] : [];

                return [
                    ...$sectionLabels,
                    ...collect($section['items'])->flatMap(function (array $item): array {
                        return [
                            $item['label'],
                            ...collect($item['children'] ?? [])->pluck('label')->all(),
                        ];
                    })->all(),
                ];
            })
            ->all();

        foreach ([
            __('Dashboard'),
            __('Clients'),
            __('Users'),
            __('Roles'),
            __('Permissions'),
            __('Products'),
            __('Services'),
            __('Billing'),
            __('Invoices'),
            __('Support'),
            __('Tickets'),
            __('Infrastructure'),
            __('Nodes'),
            __('Orders'),
            __('Settings'),
            __('Logs'),
            __('Marketplace'),
        ] as $expected) {
            $this->assertContains($expected, $labels);
        }

        $rolesItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])])
            ->firstWhere('label', __('Roles'));

        $this->assertNotNull($rolesItem);
        $this->assertFalse($rolesItem['placeholder']);
        $this->assertSame(route('admin.roles.index'), $rolesItem['url']);
    }

    public function test_navigation_hides_items_without_permission(): void
    {
        $support = User::factory()->withRole('support')->create();
        $sections = app(AdminNavigation::class)->forUser($support);

        $labels = collect($sections)
            ->flatMap(fn (array $section) => collect($section['items'])->flatMap(
                fn (array $item): array => [$item['label'], ...collect($item['children'] ?? [])->pluck('label')->all()],
            ))
            ->all();

        $this->assertContains(__('Clients'), $labels);
        $this->assertContains(__('Tickets'), $labels);
        $this->assertNotContains(__('Roles'), $labels);
        $this->assertNotContains(__('Permissions'), $labels);
        $this->assertNotContains(__('Users'), $labels);
    }

    public function test_admin_nav_component_renders_menu_and_highlights_active_route(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('aria-label="'.__('Admin menu').'"', false)
            ->assertSee(__('Dashboard'), false)
            ->assertSee(__('Roles'), false)
            ->assertSee(__('Permissions'), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('admin.roles.index'), false);
    }

    public function test_nav_blade_component_marks_placeholders(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $this->actingAs($admin);

        $html = Blade::render('<x-admin.nav />');

        $this->assertStringContainsString(__('Coming soon'), $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString(__('Products'), $html);
    }
}
