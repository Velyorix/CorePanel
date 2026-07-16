<?php

namespace Tests\Feature\License;

use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Core\License\Services\LicenseValidationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LicenseValidationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configureValidLicenseByDefault = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'cache.default' => 'array',
            'corepanel.license.validation_cache_hours' => 12,
            'corepanel.license.grace_period_hours' => 72,
            'corepanel.license.backoff_seconds' => [60, 120, 300],
            'corepanel.instance.label' => 'Production',
            'corepanel.instance.domain' => 'cms.example.com',
        ]);

        Cache::flush();
    }

    public function test_mocked_validate_success_persists_entitlements_and_activation(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-TEST-1234567890');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

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
                    'last_seen_at' => now()->toIso8601String(),
                ],
                'entitlements' => [
                    [
                        'product_type' => 'module',
                        'product_sku' => 'analytics-pro',
                        'product_name' => 'Analytics Pro',
                        'granted_at' => '2026-06-01T08:00:00+00:00',
                    ],
                    [
                        'product_type' => 'theme',
                        'product_sku' => 'dark-panel',
                        'product_name' => 'Dark Panel',
                        'granted_at' => '2026-06-01T08:00:00+00:00',
                    ],
                ],
            ], 200),
        ]);

        $state = app(LicenseValidationService::class)->validate(forceRefresh: true);

        $this->assertTrue($state->isValid);
        $this->assertSame('remote', $state->source);

        $activation = LicenseActivation::query()->where('instance_id', 'cms-prod-01')->first();

        $this->assertNotNull($activation);
        $this->assertSame('active', $activation->status);
        $this->assertSame('account', $activation->product_type);
        $this->assertSame('analytics-pro', $activation->entitlements[0]['product_sku']);
        $this->assertSame('dark-panel', $activation->entitlements[1]['product_sku']);
        $this->assertNotNull($activation->last_validated_at);
    }

    public function test_mocked_business_failure_reasons_are_surfaced_without_grace(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-BAD-KEY');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
            'last_validated_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => false,
                'reason' => 'expired',
                'message' => 'This license has expired.',
                'license' => [
                    'status' => 'expired',
                ],
            ], 403),
        ]);

        $state = app(LicenseValidationService::class)->validate(forceRefresh: true);

        $this->assertFalse($state->isValid);
        $this->assertFalse($state->inGracePeriod);
        $this->assertSame('expired', $state->reason);
        $this->assertSame('This license has expired.', $state->message);
        $this->assertSame('remote', $state->source);
    }

    public function test_grace_mode_keeps_cms_valid_when_api_is_temporarily_down(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-TEST-1234567890');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
            'last_validated_at' => now()->subHours(10),
            'entitlements' => [
                ['product_sku' => 'analytics-pro', 'product_type' => 'module', 'product_name' => 'Analytics Pro'],
            ],
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'error' => [
                    'code' => 'server_error',
                    'message' => 'Une erreur inattendue s’est produite.',
                ],
            ], 500),
        ]);

        $state = app(LicenseValidationService::class)->validate(forceRefresh: true);

        $this->assertTrue($state->isValid);
        $this->assertTrue($state->inGracePeriod);
        $this->assertSame('grace', $state->status);
        $this->assertSame('grace_period', $state->reason);
    }

    public function test_http_429_uses_grace_mode_and_applies_retry_after_backoff(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-TEST-1234567890');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
            'last_validated_at' => now()->subHours(3),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'error' => [
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Veuillez patienter avant de réessayer.',
                    'details' => [
                        'retry_after' => 90,
                    ],
                ],
            ], 429, [
                'Retry-After' => '90',
            ]),
        ]);

        $service = app(LicenseValidationService::class);

        $first = $service->validate(forceRefresh: true);
        $second = $service->validate();

        Http::assertSentCount(1);
        $this->assertTrue($first->isValid);
        $this->assertTrue($first->inGracePeriod);
        $this->assertSame('rate_limit_exceeded', $first->reason);
        $this->assertTrue($second->isValid);
        $this->assertTrue($second->inGracePeriod);
        $this->assertSame('rate_limit_exceeded', $second->reason);
    }

    public function test_backoff_schedule_increases_after_repeated_429_without_retry_after(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-TEST-1234567890');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
            'last_validated_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'error' => [
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Too many requests.',
                ],
            ], 429),
        ]);

        $service = app(LicenseValidationService::class);
        $backoffKey = 'corepanel.license.backoff.'.sha1('cms-prod-01');

        $service->validate(forceRefresh: true);
        $firstBackoff = Cache::get($backoffKey);

        $this->assertIsArray($firstBackoff);
        $this->assertSame(60, $firstBackoff['seconds']);
        $this->assertSame(1, $firstBackoff['step']);

        // Expire backoff window and drop validation cache so the next call hits the API again.
        Cache::forget('corepanel.license.validation.'.sha1('cms-prod-01'));
        Cache::put($backoffKey, [
            'until' => now()->subSecond()->toIso8601String(),
            'step' => 1,
            'seconds' => 60,
        ], now()->addMinutes(10));

        $service->validate();
        $secondBackoff = Cache::get($backoffKey);

        $this->assertIsArray($secondBackoff);
        $this->assertSame(120, $secondBackoff['seconds']);
        $this->assertSame(2, $secondBackoff['step']);
        Http::assertSentCount(2);
    }

    public function test_license_key_is_not_written_to_logs_during_validation(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-SECRET-SHOULD-NOT-LOG');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => true,
                'license' => ['status' => 'active'],
                'activation' => [],
                'entitlements' => [],
            ], 200),
        ]);

        Log::spy();

        app(LicenseValidationService::class)->validate(forceRefresh: true);

        Log::shouldNotHaveReceived('info', function (...$args): bool {
            return str_contains(json_encode($args) ?: '', 'CP-SECRET-SHOULD-NOT-LOG');
        });
        Log::shouldNotHaveReceived('debug', function (...$args): bool {
            return str_contains(json_encode($args) ?: '', 'CP-SECRET-SHOULD-NOT-LOG');
        });
        Log::shouldNotHaveReceived('error', function (...$args): bool {
            return str_contains(json_encode($args) ?: '', 'CP-SECRET-SHOULD-NOT-LOG');
        });
    }
}
