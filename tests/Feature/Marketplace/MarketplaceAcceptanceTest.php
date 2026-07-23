<?php

namespace Tests\Feature\Marketplace;

use Core\Marketplace\Enums\MarketplaceInstallAction;
use Core\Marketplace\Exceptions\MarketplaceEntitlementException;
use Core\Marketplace\Jobs\CheckMarketplaceUpdatesJob;
use Core\Marketplace\Services\MarketplaceCatalogCache;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Core\Marketplace\Services\MarketplaceUpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Acceptance smoke covering marketplace flows against a mocked corepanel.org API.
 */
class MarketplaceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.org.api_url' => 'https://corepanel.org/api/v1',
            'corepanel.org.api_token' => 'cpat_test_token_12345678901234567890',
            'corepanel.marketplace.enabled' => true,
            'corepanel.marketplace.cache.enabled' => true,
            'corepanel.marketplace.cache.store' => 'array',
            'corepanel.marketplace.cache.prefix' => 'test.marketplace.acceptance',
            'corepanel.marketplace.cache.ttl_seconds' => 3600,
            'corepanel.marketplace.entitlements.enforce' => true,
            'corepanel.marketplace.entitlements.allow_free_without_entitlement' => true,
            'corepanel.marketplace.updates.enabled' => true,
            'corepanel.marketplace.updates.cache_ttl_seconds' => 3600,
        ]);

        Cache::store('array')->flush();
        $this->app->forgetInstance(MarketplaceCatalogCache::class);
        $this->app->forgetInstance(MarketplaceClient::class);
        $this->app->forgetInstance(MarketplaceEntitlementGuard::class);
        $this->app->forgetInstance(MarketplaceUpdateChecker::class);
    }

    public function test_catalogue_is_loaded_and_served_from_cache_with_mocked_api(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::sequence()
                ->push([
                    'data' => [[
                        'id' => '1',
                        'sku' => 'MOD_A',
                        'slug' => 'mod-a',
                        'name' => 'Mod A',
                        'product_type' => 'module',
                        'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                        'current_version' => '1.0.0',
                    ]],
                    'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
                ], 200)
                ->push([
                    'data' => [],
                    'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
                ], 200),
        ]);

        $client = app(MarketplaceClient::class);
        $first = $client->listProducts(['category' => 'billing']);
        $second = $client->listProducts(['category' => 'billing']);

        $this->assertSame('mod-a', $first->items[0]->slug);
        $this->assertSame('mod-a', $second->items[0]->slug);
        Http::assertSentCount(1);
    }

    public function test_paid_install_is_blocked_without_entitlement_with_mocked_api(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/paid-pro' => Http::response([
                'data' => [
                    'id' => '2',
                    'sku' => 'MOD_PAID_PRO',
                    'slug' => 'paid-pro',
                    'name' => 'Paid Pro',
                    'product_type' => 'module',
                    'pricing' => ['is_free' => false, 'amount' => 1990, 'currency' => 'EUR'],
                    'current_version' => '1.0.0',
                ],
            ], 200),
        ]);

        $decision = app(MarketplaceEntitlementGuard::class)->evaluateSlug('paid-pro');

        $this->assertFalse($decision->canInstall());
        $this->assertSame(MarketplaceInstallAction::Purchase, $decision->action);

        $this->expectException(MarketplaceEntitlementException::class);
        app(MarketplaceEntitlementGuard::class)->assertCanInstallSlug('paid-pro');
    }

    public function test_update_check_job_is_registered_on_scheduler(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(CheckMarketplaceUpdatesJob::class, $output);
    }
}
