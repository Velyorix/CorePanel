<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Core\Client\Navigation\ClientNavigation;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ClientNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-nav',
            'corepanel.themes.auto_load_active' => false,
        ]);

        $this->withoutVite();
    }

    public function test_navigation_includes_tome7_structure(): void
    {
        $client = User::factory()->withRole('client')->create();
        $sections = app(ClientNavigation::class)->forUser($client);

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
            __('Catalog'),
            __('Cart'),
            __('Orders'),
            __('Services'),
            __('Invoices'),
            __('Payments'),
            __('Tickets'),
            __('Account'),
            __('Profile'),
            __('Security'),
            __('Guest users'),
            __('API Keys'),
            __('Marketplace'),
            __('Modules'),
            __('Themes'),
            __('Purchases'),
            __('Support'),
            __('Open ticket'),
            __('My tickets'),
            __('Settings'),
            __('Notifications'),
            __('Preferences'),
        ] as $expected) {
            $this->assertContains($expected, $labels);
        }
    }

    public function test_navigation_hides_client_items_for_admin_without_client_permissions(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $sections = app(ClientNavigation::class)->forUser($admin);

        $labels = collect($sections)
            ->flatMap(fn (array $section) => collect($section['items'])->pluck('label'))
            ->all();

        $this->assertNotContains(__('Modules'), $labels);
        $this->assertNotContains(__('Themes'), $labels);
        $this->assertNotContains(__('Purchases'), $labels);
        $this->assertNotContains(__('Services'), $labels);
        $this->assertNotContains(__('Invoices'), $labels);
        $this->assertNotContains(__('Orders'), $labels);
        $this->assertNotContains(__('Profile'), $labels);
    }

    public function test_client_nav_component_renders_menu_and_marks_placeholders(): void
    {
        $client = User::factory()->withRole('client')->create();
        $this->actingAs($client);

        $html = Blade::render('<x-client.nav />');

        $this->assertStringContainsString('aria-label="'.__('Client menu').'"', $html);
        $this->assertStringContainsString(__('Services'), $html);
        $this->assertStringContainsString(route('client.services.index'), $html);
        $this->assertStringContainsString(__('Guest users'), $html);
        $this->assertStringContainsString(__('Open ticket'), $html);
        $this->assertStringContainsString(__('Coming soon'), $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    public function test_client_layout_renders_default_navigation(): void
    {
        $client = User::factory()->withRole('client')->create();
        $this->actingAs($client);

        $html = Blade::render(<<<'BLADE'
            <x-layout.client title="Overview" page-heading="Overview">
                Overview body
            </x-layout.client>
        BLADE);

        $this->assertStringContainsString('aria-label="'.__('Client menu').'"', $html);
        $this->assertStringContainsString(__('Services'), $html);
        $this->assertStringContainsString(__('My tickets'), $html);
    }

    public function test_dashboard_item_links_to_client_dashboard_route(): void
    {
        $client = User::factory()->withRole('client')->create();
        $sections = app(ClientNavigation::class)->forUser($client);

        $dashboardItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Dashboard'));

        $this->assertNotNull($dashboardItem);
        $this->assertFalse($dashboardItem['placeholder']);
        $this->assertSame(route('client.dashboard'), $dashboardItem['url']);
    }

    public function test_catalog_item_links_to_client_catalog_route(): void
    {
        $client = User::factory()->withRole('client')->create();
        $sections = app(ClientNavigation::class)->forUser($client);

        $catalogItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Catalog'));

        $this->assertNotNull($catalogItem);
        $this->assertFalse($catalogItem['placeholder']);
        $this->assertSame(route('client.catalog.index'), $catalogItem['url']);
    }

    public function test_cart_item_links_to_client_cart_route(): void
    {
        $client = User::factory()->withRole('client')->create();
        $sections = app(ClientNavigation::class)->forUser($client);

        $cartItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Cart'));

        $this->assertNotNull($cartItem);
        $this->assertFalse($cartItem['placeholder']);
        $this->assertSame(route('client.cart.index'), $cartItem['url']);
    }

    public function test_orders_item_links_to_client_orders_route(): void
    {
        $client = User::factory()->withRole('client')->create();
        $sections = app(ClientNavigation::class)->forUser($client);

        $ordersItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Orders'));

        $this->assertNotNull($ordersItem);
        $this->assertFalse($ordersItem['placeholder']);
        $this->assertSame(route('client.orders.index'), $ordersItem['url']);
    }

    public function test_services_item_links_to_client_services_route(): void
    {
        $client = User::factory()->withRole('client')->create();
        $sections = app(ClientNavigation::class)->forUser($client);

        $servicesItem = collect($sections)
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Services'));

        $this->assertNotNull($servicesItem);
        $this->assertFalse($servicesItem['placeholder']);
        $this->assertSame(route('client.services.index'), $servicesItem['url']);
    }

    public function test_marketplace_items_link_to_client_marketplace_routes(): void
    {
        $client = User::factory()->withRole('client')->create();
        $sections = app(ClientNavigation::class)->forUser($client);
        $items = collect($sections)->flatMap(fn (array $section) => $section['items']);

        $modulesItem = $items->firstWhere('label', __('Modules'));
        $themesItem = $items->firstWhere('label', __('Themes'));
        $purchasesItem = $items->firstWhere('label', __('Purchases'));

        $this->assertNotNull($modulesItem);
        $this->assertFalse($modulesItem['placeholder']);
        $this->assertSame(route('client.marketplace.index', ['product_type' => 'module']), $modulesItem['url']);

        $this->assertNotNull($themesItem);
        $this->assertFalse($themesItem['placeholder']);
        $this->assertSame(route('client.marketplace.index', ['product_type' => 'theme']), $themesItem['url']);

        $this->assertNotNull($purchasesItem);
        $this->assertFalse($purchasesItem['placeholder']);
        $this->assertSame(route('client.marketplace.purchases'), $purchasesItem['url']);
    }
}
