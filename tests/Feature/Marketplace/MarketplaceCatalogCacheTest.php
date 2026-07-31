<?php

namespace Tests\Feature\Marketplace;

use Core\Marketplace\Services\MarketplaceCatalogCache;
use Core\Marketplace\Services\MarketplaceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceCatalogCacheTest extends TestCase
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
            'corepanel.marketplace.cache.prefix' => 'test.marketplace',
            'corepanel.marketplace.cache.ttl_seconds' => 3600,
        ]);

        Cache::store('array')->flush();
        $this->app->forgetInstance(MarketplaceCatalogCache::class);
        $this->app->forgetInstance(MarketplaceClient::class);
    }

    public function test_list_products_is_served_from_cache_on_second_call(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::sequence()
                ->push([
                    'data' => [
                        [
                            'id' => '1',
                            'sku' => 'MOD_A',
                            'slug' => 'mod-a',
                            'name' => 'Mod A',
                            'product_type' => 'module',
                            'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                            'current_version' => '1.0.0',
                        ],
                    ],
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

    public function test_different_filters_use_different_cache_entries(): void
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
                        'pricing' => ['is_free' => true],
                    ]],
                    'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
                ], 200)
                ->push([
                    'data' => [[
                        'id' => '2',
                        'sku' => 'THM_B',
                        'slug' => 'thm-b',
                        'name' => 'Theme B',
                        'product_type' => 'theme',
                        'pricing' => ['is_free' => true],
                    ]],
                    'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
                ], 200),
        ]);

        $client = app(MarketplaceClient::class);

        $modules = $client->listProducts(['product_type' => 'module']);
        $themes = $client->listProducts(['product_type' => 'theme']);

        $this->assertSame('mod-a', $modules->items[0]->slug);
        $this->assertSame('thm-b', $themes->items[0]->slug);
        Http::assertSentCount(2);
    }

    public function test_product_and_versions_are_cached_independently(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/stripe-billing-pro' => Http::response([
                'data' => [
                    'id' => '1',
                    'sku' => 'MOD_STRIPE',
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
                    'compatibility' => ['min_version' => '1.0.0'],
                ]],
            ], 200),
        ]);

        $client = app(MarketplaceClient::class);

        $this->assertSame('1.2.0', $client->getProduct('stripe-billing-pro')->currentVersion);
        $this->assertSame('1.2.0', $client->listVersions('stripe-billing-pro')->latest()?->version);

        $this->assertSame('1.2.0', $client->getProduct('stripe-billing-pro')->currentVersion);
        $this->assertSame('1.2.0', $client->listVersions('stripe-billing-pro')->latest()?->version);

        Http::assertSentCount(2);
    }

    public function test_flush_cache_forces_refetch(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::sequence()
                ->push([
                    'data' => [[
                        'id' => '1',
                        'sku' => 'MOD_A',
                        'slug' => 'first',
                        'name' => 'First',
                        'product_type' => 'module',
                        'pricing' => ['is_free' => true],
                    ]],
                    'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
                ], 200)
                ->push([
                    'data' => [[
                        'id' => '2',
                        'sku' => 'MOD_B',
                        'slug' => 'second',
                        'name' => 'Second',
                        'product_type' => 'module',
                        'pricing' => ['is_free' => true],
                    ]],
                    'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
                ], 200),
        ]);

        $client = app(MarketplaceClient::class);

        $this->assertSame('first', $client->listProducts()->items[0]->slug);

        $client->flushCache();

        $this->assertSame('second', $client->listProducts()->items[0]->slug);
        Http::assertSentCount(2);
    }

    public function test_forget_product_cache_invalidates_detail_and_versions(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/mod-a' => Http::sequence()
                ->push([
                    'data' => [
                        'id' => '1',
                        'sku' => 'MOD_A',
                        'slug' => 'mod-a',
                        'name' => 'Mod A v1',
                        'product_type' => 'module',
                        'pricing' => ['is_free' => true],
                        'current_version' => '1.0.0',
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'id' => '1',
                        'sku' => 'MOD_A',
                        'slug' => 'mod-a',
                        'name' => 'Mod A v2',
                        'product_type' => 'module',
                        'pricing' => ['is_free' => true],
                        'current_version' => '2.0.0',
                    ],
                ], 200),
            'https://corepanel.org/api/v1/marketplace/products/mod-a/versions' => Http::sequence()
                ->push([
                    'product' => ['slug' => 'mod-a'],
                    'data' => [[
                        'id' => 'v1',
                        'version' => '1.0.0',
                        'is_latest' => true,
                        'has_archive' => true,
                        'compatibility' => [],
                    ]],
                ], 200)
                ->push([
                    'product' => ['slug' => 'mod-a'],
                    'data' => [[
                        'id' => 'v2',
                        'version' => '2.0.0',
                        'is_latest' => true,
                        'has_archive' => true,
                        'compatibility' => [],
                    ]],
                ], 200),
        ]);

        $client = app(MarketplaceClient::class);

        $this->assertSame('1.0.0', $client->getProduct('mod-a')->currentVersion);
        $this->assertSame('1.0.0', $client->listVersions('mod-a')->latest()?->version);

        $client->forgetProductCache('mod-a');

        $this->assertSame('2.0.0', $client->getProduct('mod-a')->currentVersion);
        $this->assertSame('2.0.0', $client->listVersions('mod-a')->latest()?->version);
        Http::assertSentCount(4);
    }

    public function test_disabled_cache_always_hits_api(): void
    {
        config(['corepanel.marketplace.cache.enabled' => false]);
        $this->app->forgetInstance(MarketplaceCatalogCache::class);
        $this->app->forgetInstance(MarketplaceClient::class);

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ], 200),
        ]);

        $client = app(MarketplaceClient::class);
        $client->listProducts();
        $client->listProducts();

        Http::assertSentCount(2);
    }

    public function test_cache_respects_configured_ttl_seconds(): void
    {
        config(['corepanel.marketplace.cache.ttl_seconds' => 120]);
        $this->app->forgetInstance(MarketplaceCatalogCache::class);

        $cache = app(MarketplaceCatalogCache::class);

        $this->assertSame(120, $cache->ttlSeconds());
        $this->assertTrue($cache->enabled());
    }
}
