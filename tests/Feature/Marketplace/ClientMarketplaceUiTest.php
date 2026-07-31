<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Core\Marketplace\Services\MarketplaceCatalogCache;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientMarketplaceUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.org.api_url' => 'https://corepanel.org/api/v1',
            'corepanel.org.api_token' => 'cpat_test_token_12345678901234567890',
            'corepanel.org.store_url' => 'https://corepanel.org',
            'corepanel.version' => '1.2.0',
            'corepanel.marketplace.enabled' => true,
            'corepanel.marketplace.cache.enabled' => false,
            'corepanel.marketplace.cache.store' => 'array',
            'corepanel.marketplace.cache.prefix' => 'test.marketplace.client',
            'corepanel.marketplace.entitlements.enforce' => true,
            'corepanel.marketplace.entitlements.allow_free_without_entitlement' => true,
            'corepanel.marketplace.compatibility.enforce' => true,
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.marketplace-client',
            'corepanel.themes.auto_load_active' => false,
        ]);

        Cache::store('array')->flush();
        $this->configureValidLicense();
        $this->withoutVite();
        $this->app->forgetInstance(MarketplaceCatalogCache::class);
        $this->app->forgetInstance(MarketplaceClient::class);
        $this->app->forgetInstance(MarketplaceEntitlementGuard::class);
        $this->app->forgetInstance(MarketplaceCompatibilityGuard::class);
    }

    public function test_client_can_browse_marketplace_catalogue(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [[
                    'id' => '1',
                    'sku' => 'MOD_DEMO',
                    'slug' => 'marketplace-demo',
                    'name' => 'Marketplace Demo',
                    'short_description' => 'Demo package',
                    'product_type' => 'module',
                    'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                    'current_version' => '1.0.0',
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
            ], 200),
        ]);

        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.marketplace.index'))
            ->assertOk()
            ->assertSee('Marketplace Demo')
            ->assertSee('marketplace-demo')
            ->assertSee(__('Free'));
    }

    public function test_client_can_view_paid_product_with_external_buy_link(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/stripe-billing-pro' => Http::response([
                'data' => [
                    'id' => '2',
                    'sku' => 'MOD_STRIPE_BILLING_PRO',
                    'slug' => 'stripe-billing-pro',
                    'name' => 'Stripe Billing Pro',
                    'product_type' => 'module',
                    'pricing' => ['is_free' => false, 'amount' => 2990, 'currency' => 'EUR'],
                    'current_version' => '1.2.0',
                ],
            ], 200),
            'https://corepanel.org/api/v1/marketplace/products/stripe-billing-pro/versions' => Http::response([
                'product' => ['slug' => 'stripe-billing-pro', 'name' => 'Stripe Billing Pro'],
                'data' => [[
                    'id' => 'v1',
                    'version' => '1.2.0',
                    'is_latest' => true,
                    'has_archive' => true,
                    'compatibility' => ['min_version' => '1.0.0', 'max_version' => null],
                ]],
            ], 200),
        ]);

        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.marketplace.show', 'stripe-billing-pro'))
            ->assertOk()
            ->assertSee('Stripe Billing Pro')
            ->assertSee(__('Buy on CorePanel.org'))
            ->assertSee('https://corepanel.org/marketplace/stripe-billing-pro', false)
            ->assertSee(__('Compatible'));
    }

    public function test_client_can_view_purchases_from_license_entitlements(): void
    {
        $instanceId = (string) app(LicenseSettings::class)->instanceId();

        LicenseActivation::query()->updateOrCreate(
            ['instance_id' => $instanceId],
            [
                'status' => 'active',
                'entitlements' => [[
                    'product_type' => 'module',
                    'product_sku' => 'analytics-pro',
                    'product_name' => 'Analytics Pro',
                    'granted_at' => '2026-06-01T08:00:00+00:00',
                ]],
                'updated_at' => now(),
            ],
        );

        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.marketplace.purchases'))
            ->assertOk()
            ->assertSee('Analytics Pro')
            ->assertSee('analytics-pro')
            ->assertSee(__('View on CorePanel.org'));
    }

    public function test_forbidden_without_client_marketplace_permission(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.marketplace.index'))
            ->assertForbidden();
    }

    public function test_client_catalogue_forwards_product_type_filter_to_api(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [[
                    'id' => '3',
                    'sku' => 'THM_OCEAN',
                    'slug' => 'ocean-blue',
                    'name' => 'Ocean Blue',
                    'product_type' => 'theme',
                    'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                    'current_version' => '2.0.0',
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
            ], 200),
        ]);

        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.marketplace.index', ['product_type' => 'theme']))
            ->assertOk()
            ->assertSee(__('Marketplace themes'))
            ->assertSee('Ocean Blue');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/marketplace/products')
                && $request['product_type'] === 'theme';
        });
    }

    public function test_client_can_browse_free_theme_catalogue(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [[
                    'id' => '4',
                    'sku' => 'THM_AURORA',
                    'slug' => 'aurora',
                    'name' => 'Aurora Theme',
                    'product_type' => 'theme',
                    'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                    'current_version' => '1.1.0',
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
            ], 200),
        ]);

        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.marketplace.index', ['product_type' => 'theme']))
            ->assertOk()
            ->assertSee('Aurora Theme')
            ->assertSee('Theme')
            ->assertSee(__('Free'));
    }

    public function test_client_marketplace_returns_not_found_when_disabled(): void
    {
        config(['corepanel.marketplace.enabled' => false]);

        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('client.marketplace.index'))
            ->assertNotFound();
    }
}
