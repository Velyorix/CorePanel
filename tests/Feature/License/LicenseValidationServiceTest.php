<?php

namespace Tests\Feature\License;

use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Core\License\Services\LicenseValidationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseValidationServiceTest extends TestCase
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
            'corepanel.instance.label' => 'Production',
            'corepanel.instance.domain' => 'cms.example.com',
        ]);

        Cache::flush();
    }

    public function test_validation_success_is_cached_for_ttl_and_remote_called_once(): void
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
                ],
                'activation' => [
                    'instance_id' => 'cms-prod-01',
                    'status' => 'active',
                ],
                'entitlements' => [],
            ], 200),
        ]);

        $service = app(LicenseValidationService::class);

        $first = $service->validate();
        $second = $service->validate();

        Http::assertSentCount(1);
        $this->assertTrue($first->isValid);
        $this->assertFalse($first->inGracePeriod);
        $this->assertTrue($second->isValid);
        $this->assertSame('cache', $second->source);
    }

    public function test_validation_uses_grace_mode_when_request_fails_within_72_hours(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-TEST-1234567890');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
            'last_validated_at' => now()->subHours(2),
            'updated_at' => now(),
        ]);

        Http::fake(function (): never {
            throw new ConnectionException('CorePanel.org unreachable');
        });

        $state = app(LicenseValidationService::class)->validate(forceRefresh: true);

        $this->assertTrue($state->isValid);
        $this->assertTrue($state->inGracePeriod);
        $this->assertSame('grace', $state->status);
        $this->assertSame('grace', $state->source);
    }

    public function test_validation_fails_when_grace_window_is_expired(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-TEST-1234567890');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
            'last_validated_at' => now()->subHours(80),
            'updated_at' => now(),
        ]);

        Http::fake(function (): never {
            throw new ConnectionException('CorePanel.org unreachable');
        });

        $state = app(LicenseValidationService::class)->validate(forceRefresh: true);

        $this->assertFalse($state->isValid);
        $this->assertFalse($state->inGracePeriod);
        $this->assertSame('request_failed', $state->reason);
    }

    public function test_validation_returns_missing_configuration_when_license_settings_absent(): void
    {
        $state = app(LicenseValidationService::class)->validate();

        $this->assertFalse($state->isValid);
        $this->assertSame('missing_configuration', $state->status);
        $this->assertSame('local', $state->source);
    }
}

