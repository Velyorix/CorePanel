<?php

namespace Tests\Feature\Marketplace;

use Core\Marketplace\Exceptions\MarketplaceApiException;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Support\MarketplaceCatalogCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.org.api_url' => 'https://corepanel.org/api/v1',
            'corepanel.org.api_token' => 'cpat_test_token_12345678901234567890',
            'corepanel.org.timeout_seconds' => 10,
            'corepanel.marketplace.enabled' => true,
            'corepanel.marketplace.cache.enabled' => false,
        ]);
    }

    public function test_marketplace_client_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(MarketplaceClient::class),
            app(MarketplaceClient::class),
        );
    }

    public function test_list_products_sends_filters_and_parses_paginated_catalogue(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [
                    [
                        'id' => '11111111-1111-1111-1111-111111111111',
                        'sku' => 'MOD_STRIPE_BILLING_PRO',
                        'slug' => 'stripe-billing-pro',
                        'name' => 'Stripe Billing Pro',
                        'short_description' => 'Stripe payments for CorePanel',
                        'product_type' => 'module',
                        'category' => ['id' => 'c1', 'slug' => 'billing', 'name' => 'Billing'],
                        'developer' => ['id' => 'd1', 'name' => 'Velyorix'],
                        'pricing' => ['is_free' => false, 'amount' => 2990, 'currency' => 'EUR'],
                        'compatibility' => ['min_version' => '1.0.0', 'max_version' => null],
                        'ratings' => ['average' => 460, 'count' => 24],
                        'stats' => ['download_count' => 1840],
                        'current_version' => '1.2.0',
                        'is_vip' => true,
                        'published_at' => '2026-05-01T00:00:00+00:00',
                        'thumbnail_url' => 'https://corepanel.org/assets/stripe.png',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ], 200),
        ]);

        $page = app(MarketplaceClient::class)->listProducts([
            'category' => 'billing',
            'product_type' => 'module',
            'sort' => 'downloads',
            'page' => 1,
            'per_page' => 20,
        ]);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://corepanel.org/api/v1/marketplace/products')
                && $request['category'] === 'billing'
                && $request['product_type'] === 'module'
                && $request['sort'] === 'downloads'
                && (int) $request['page'] === 1
                && (int) $request['per_page'] === 20
                && $request->hasHeader('Authorization', 'Bearer cpat_test_token_12345678901234567890')
                && $request->hasHeader('Accept', 'application/json');
        });

        $this->assertCount(1, $page->items);
        $this->assertSame(1, $page->total());
        $this->assertSame('stripe-billing-pro', $page->items[0]->slug);
        $this->assertTrue($page->items[0]->isModule());
        $this->assertFalse($page->items[0]->isFree());
        $this->assertSame(2990, $page->items[0]->pricing['amount']);
        $this->assertSame('1.2.0', $page->items[0]->currentVersion);
    }

    public function test_get_product_parses_data_envelope(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/ocean-theme' => Http::response([
                'data' => [
                    'id' => '22222222-2222-2222-2222-222222222222',
                    'sku' => 'THM_OCEAN',
                    'slug' => 'ocean-theme',
                    'name' => 'Ocean Theme',
                    'short_description' => null,
                    'product_type' => 'theme',
                    'category' => ['slug' => 'themes', 'name' => 'Themes'],
                    'developer' => ['name' => 'Velyorix'],
                    'pricing' => ['is_free' => true, 'amount' => 0, 'currency' => 'EUR'],
                    'compatibility' => ['min_version' => '1.0.0'],
                    'ratings' => ['average' => 500, 'count' => 3],
                    'stats' => ['download_count' => 90],
                    'current_version' => '2.0.0',
                    'is_vip' => false,
                    'published_at' => '2026-06-01T00:00:00+00:00',
                    'thumbnail_url' => null,
                ],
            ], 200),
        ]);

        $product = app(MarketplaceClient::class)->getProduct('ocean-theme');

        $this->assertSame('ocean-theme', $product->slug);
        $this->assertTrue($product->isTheme());
        $this->assertTrue($product->isFree());
        $this->assertSame('2.0.0', $product->currentVersion);
    }

    public function test_list_versions_parses_mixed_product_and_data_envelope(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/stripe-billing-pro/versions' => Http::response([
                'product' => [
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'slug' => 'stripe-billing-pro',
                    'name' => 'Stripe Billing Pro',
                ],
                'data' => [
                    [
                        'id' => 'v2',
                        'version' => '1.2.0',
                        'is_latest' => true,
                        'changelog' => 'Bug fixes',
                        'compatibility' => ['min_version' => '1.0.0', 'max_version' => null],
                        'dependencies' => [],
                        'has_archive' => true,
                        'archive_size_bytes' => 1048576,
                        'published_at' => '2026-06-01T00:00:00+00:00',
                    ],
                    [
                        'id' => 'v1',
                        'version' => '1.1.0',
                        'is_latest' => false,
                        'changelog' => 'Initial',
                        'compatibility' => ['min_version' => '1.0.0'],
                        'dependencies' => [],
                        'has_archive' => true,
                        'archive_size_bytes' => 900000,
                        'published_at' => '2026-05-01T00:00:00+00:00',
                    ],
                ],
            ], 200),
        ]);

        $list = app(MarketplaceClient::class)->listVersions('stripe-billing-pro');

        $this->assertSame('stripe-billing-pro', $list->product['slug']);
        $this->assertCount(2, $list->versions);
        $this->assertSame('1.2.0', $list->latest()?->version);
        $this->assertTrue($list->latest()?->hasArchive);
        $this->assertSame('1.1.0', $list->find('1.1.0')?->version);
        $this->assertSame('1.0.0', $list->latest()?->minCmsVersion());
    }

    public function test_get_version_parses_single_version_payload(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/stripe-billing-pro/versions/1.2.0' => Http::response([
                'product' => [
                    'slug' => 'stripe-billing-pro',
                    'name' => 'Stripe Billing Pro',
                ],
                'data' => [
                    'id' => 'v2',
                    'version' => '1.2.0',
                    'is_latest' => true,
                    'changelog' => 'Bug fixes',
                    'compatibility' => ['min_version' => '1.0.0'],
                    'dependencies' => [],
                    'has_archive' => true,
                    'archive_size_bytes' => 1048576,
                    'published_at' => '2026-06-01T00:00:00+00:00',
                ],
            ], 200),
        ]);

        $version = app(MarketplaceClient::class)->getVersion('stripe-billing-pro', '1.2.0');

        $this->assertSame('1.2.0', $version->version);
        $this->assertTrue($version->isLatest);
        $this->assertSame(1048576, $version->archiveSizeBytes);
    }

    public function test_uses_built_in_catalogue_token_when_config_token_is_empty(): void
    {
        config(['corepanel.org.api_token' => null]);

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ], 200),
        ]);

        app(MarketplaceClient::class)->listProducts();

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer '.MarketplaceCatalogCredentials::TOKEN);
        });
    }

    public function test_env_api_token_overrides_built_in_catalogue_token(): void
    {
        config(['corepanel.org.api_token' => 'cpat_override_token_for_staging_tests']);

        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ], 200),
        ]);

        app(MarketplaceClient::class)->listProducts();

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer cpat_override_token_for_staging_tests');
        });
    }

    public function test_api_error_envelope_is_mapped_to_exception(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/missing' => Http::response([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Product not found.',
                ],
            ], 404),
        ]);

        try {
            app(MarketplaceClient::class)->getProduct('missing');
            $this->fail('Expected MarketplaceApiException was not thrown.');
        } catch (MarketplaceApiException $exception) {
            $this->assertSame(404, $exception->statusCode);
            $this->assertSame('not_found', $exception->errorCode);
            $this->assertSame('Product not found.', $exception->getMessage());
        }
    }

    public function test_rate_limit_reads_retry_after_header(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products*' => Http::response([
                'error' => [
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Too many requests.',
                    'details' => ['retry_after' => 30],
                ],
            ], 429, [
                'Retry-After' => '42',
            ]),
        ]);

        try {
            app(MarketplaceClient::class)->listProducts();
            $this->fail('Expected MarketplaceApiException was not thrown.');
        } catch (MarketplaceApiException $exception) {
            $this->assertSame(429, $exception->statusCode);
            $this->assertSame('rate_limit_exceeded', $exception->errorCode);
            $this->assertSame(42, $exception->retryAfter);
        }
    }

    public function test_connection_failure_is_wrapped(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection refused.');
        });

        try {
            app(MarketplaceClient::class)->listProducts();
            $this->fail('Expected MarketplaceApiException was not thrown.');
        } catch (MarketplaceApiException $exception) {
            $this->assertSame('request_failed', $exception->errorCode);
            $this->assertSame('Connection refused.', $exception->getMessage());
            $this->assertNull($exception->statusCode);
        }
    }

    public function test_client_uses_configured_base_url(): void
    {
        config(['corepanel.org.api_url' => 'https://staging.corepanel.test/api/v1']);

        Http::fake([
            'https://staging.corepanel.test/api/v1/marketplace/products*' => Http::response([
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ], 200),
        ]);

        app(MarketplaceClient::class)->listProducts();

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://staging.corepanel.test/api/v1/marketplace/products');
        });
    }

    public function test_request_download_returns_temporary_archive_descriptor(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/marketplace/products/demo/versions/1.0.0/download' => Http::response([
                'data' => [
                    'download_url' => 'https://cdn.corepanel.test/demo-1.0.0.zip?token=abc',
                    'filename' => 'demo-1.0.0.zip',
                    'checksum_sha256' => str_repeat('b', 64),
                    'size_bytes' => 2048,
                    'expires_at' => '2026-07-23T12:00:00+00:00',
                ],
            ], 200),
        ]);

        $descriptor = app(MarketplaceClient::class)->requestDownload('demo', '1.0.0');

        $this->assertSame('https://cdn.corepanel.test/demo-1.0.0.zip?token=abc', $descriptor->downloadUrl);
        $this->assertSame('demo-1.0.0.zip', $descriptor->filename);
        $this->assertSame(str_repeat('b', 64), $descriptor->checksumSha256);
        $this->assertSame(2048, $descriptor->sizeBytes);
    }
}
