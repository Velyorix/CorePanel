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
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->withoutVite();
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
            __('Catalog'),
            __('Categories'),
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

        $flatItems = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])]);

        $dashboardItem = $flatItems->firstWhere('label', __('Dashboard'));
        $rolesItem = $flatItems->firstWhere('label', __('Roles'));
        $clientsItem = $flatItems->firstWhere('label', __('Clients'));
        $catalogItem = $flatItems->firstWhere('label', __('Catalog'));
        $categoriesItem = $flatItems->firstWhere('label', __('Categories'));
        $ordersItem = $flatItems->firstWhere('label', __('Orders'));
        $marketplaceItem = $flatItems->firstWhere('label', __('Marketplace'));

        $this->assertNotNull($dashboardItem);
        $this->assertFalse($dashboardItem['placeholder']);
        $this->assertSame(route('admin.dashboard'), $dashboardItem['url']);

        $this->assertNotNull($rolesItem);
        $this->assertFalse($rolesItem['placeholder']);
        $this->assertSame(route('admin.roles.index'), $rolesItem['url']);

        $this->assertNotNull($clientsItem);
        $this->assertFalse($clientsItem['placeholder']);
        $this->assertSame(route('admin.clients.index'), $clientsItem['url']);

        $this->assertNotNull($catalogItem);
        $this->assertFalse($catalogItem['placeholder']);
        $this->assertSame(route('admin.products.index'), $catalogItem['url']);

        $this->assertNotNull($categoriesItem);
        $this->assertFalse($categoriesItem['placeholder']);
        $this->assertSame(route('admin.product-categories.index'), $categoriesItem['url']);

        $this->assertNotNull($ordersItem);
        $this->assertFalse($ordersItem['placeholder']);
        $this->assertSame(route('admin.orders.index'), $ordersItem['url']);

        $this->assertNotNull($marketplaceItem);
        $this->assertFalse($marketplaceItem['placeholder']);
        $this->assertSame(route('admin.marketplace.index'), $marketplaceItem['url']);

        $servicesItem = $flatItems->firstWhere('label', __('Services'));
        $this->assertNotNull($servicesItem);
        $this->assertFalse($servicesItem['placeholder']);
        $this->assertSame(route('admin.services.index'), $servicesItem['url']);

        $nodesItem = $flatItems->firstWhere('label', __('Nodes'));
        $this->assertNotNull($nodesItem);
        $this->assertFalse($nodesItem['placeholder']);
        $this->assertSame(route('admin.nodes.index'), $nodesItem['url']);

        $groupsItem = $flatItems->firstWhere('label', __('Groups'));
        $this->assertNotNull($groupsItem);
        $this->assertFalse($groupsItem['placeholder']);
        $this->assertSame(route('admin.node-groups.index'), $groupsItem['url']);
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
        $this->assertContains(__('Products'), $labels);
        $this->assertContains(__('Catalog'), $labels);
        $this->assertContains(__('Orders'), $labels);
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
        $this->assertStringContainsString(__('Tickets'), $html);
        $this->assertStringContainsString(route('admin.services.index'), $html);
    }
}
