<?php

namespace Tests\Feature\License;

use Core\License\Services\CorePanelOrgClient;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CorePanelOrgClientTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configureValidLicenseByDefault = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_validate_license_posts_expected_payload_and_parses_success_response(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => true,
                'license' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'status' => 'active',
                    'product_type' => 'account',
                    'expires_at' => null,
                ],
                'activation' => [
                    'instance_id' => 'cms-prod-01',
                    'status' => 'active',
                    'domain' => 'cms.example.com',
                ],
                'entitlements' => [
                    [
                        'product_type' => 'module',
                        'product_sku' => 'analytics-pro',
                        'product_name' => 'Analytics Pro',
                    ],
                ],
            ], 200),
        ]);

        $result = app(CorePanelOrgClient::class)->validateLicense(
            licenseKey: 'CP-TEST-1234567890',
            instanceId: 'cms-prod-01',
            instanceLabel: 'Production',
            domain: 'cms.example.com',
            metadata: [
                'cms_version' => '0.0.0-dev',
                'php_version' => PHP_VERSION,
            ],
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://corepanel.org/api/v1/licenses/validate'
                && $request['license_key'] === 'CP-TEST-1234567890'
                && $request['instance_id'] === 'cms-prod-01'
                && $request['instance_label'] === 'Production'
                && $request['domain'] === 'cms.example.com'
                && $request['metadata']['cms_version'] === '0.0.0-dev'
                && $request->hasHeader('Accept', 'application/json')
                && str_contains($request->header('Content-Type')[0] ?? '', 'application/json');
        });

        $this->assertTrue($result->valid);
        $this->assertSame(200, $result->statusCode);
        $this->assertSame('active', $result->license['status']);
        $this->assertSame('cms-prod-01', $result->activation['instance_id']);
        $this->assertSame('analytics-pro', $result->entitlements[0]['product_sku']);
    }

    public function test_validate_license_parses_business_failure_response(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => false,
                'reason' => 'invalid_key',
                'message' => 'The provided license key is invalid.',
            ], 403),
        ]);

        $result = app(CorePanelOrgClient::class)->validateLicense(
            licenseKey: 'CP-BAD',
            instanceId: 'cms-prod-01',
        );

        $this->assertFalse($result->valid);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('invalid_key', $result->reason);
        $this->assertSame('The provided license key is invalid.', $result->message);
    }

    public function test_validate_license_returns_request_failed_result_on_connection_error(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection refused.');
        });

        $result = app(CorePanelOrgClient::class)->validateLicense(
            licenseKey: 'CP-TEST-1234567890',
            instanceId: 'cms-prod-01',
        );

        $this->assertFalse($result->valid);
        $this->assertNull($result->statusCode);
        $this->assertSame('request_failed', $result->reason);
        $this->assertSame('Connection refused.', $result->message);
    }

    public function test_validate_license_uses_configured_base_url(): void
    {
        config([
            'corepanel.org.api_url' => 'https://staging.corepanel.test/api/v1',
            'corepanel.org.timeout_seconds' => 22,
        ]);

        Http::fake([
            'https://staging.corepanel.test/api/v1/licenses/validate' => Http::response([
                'valid' => true,
            ], 200),
        ]);

        app(CorePanelOrgClient::class)->validateLicense(
            licenseKey: 'CP-TEST-1234567890',
            instanceId: 'cms-prod-02',
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://staging.corepanel.test/api/v1/licenses/validate';
        });
    }

    public function test_validate_license_parses_standard_error_envelope(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'error' => [
                    'code' => 'server_error',
                    'message' => 'Une erreur inattendue s’est produite.',
                ],
            ], 500),
        ]);

        $result = app(CorePanelOrgClient::class)->validateLicense(
            licenseKey: 'CP-TEST-1234567890',
            instanceId: 'cms-prod-01',
        );

        $this->assertFalse($result->valid);
        $this->assertSame(500, $result->statusCode);
        $this->assertSame('server_error', $result->reason);
        $this->assertSame('Une erreur inattendue s’est produite.', $result->message);
    }

    public function test_validate_license_reads_retry_after_from_429_response(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'error' => [
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Too many requests.',
                    'details' => [
                        'retry_after' => 42,
                    ],
                ],
            ], 429, [
                'Retry-After' => '42',
            ]),
        ]);

        $result = app(CorePanelOrgClient::class)->validateLicense(
            licenseKey: 'CP-TEST-1234567890',
            instanceId: 'cms-prod-01',
        );

        $this->assertFalse($result->valid);
        $this->assertSame(429, $result->statusCode);
        $this->assertSame('rate_limit_exceeded', $result->reason);
        $this->assertSame(42, $result->retryAfter);
    }

    public function test_validate_license_omits_blank_optional_fields(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => true,
            ], 200),
        ]);

        app(CorePanelOrgClient::class)->validateLicense(
            licenseKey: 'CP-TEST-1234567890',
            instanceId: 'cms-prod-01',
            instanceLabel: '',
            domain: '',
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://corepanel.org/api/v1/licenses/validate'
                && $request['license_key'] === 'CP-TEST-1234567890'
                && $request['instance_id'] === 'cms-prod-01'
                && ! array_key_exists('instance_label', $request->data())
                && ! array_key_exists('domain', $request->data());
        });
    }
}

