<?php

namespace Tests\Feature\License;

use Core\License\Models\LicenseActivation;
use Core\License\Services\EntitlementService;
use Core\License\Services\LicenseSettings;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configureValidLicenseByDefault = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_entitlement_service_exposes_modules_and_themes_from_active_activation(): void
    {
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
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
            'updated_at' => now(),
        ]);

        $service = app(EntitlementService::class);

        $this->assertTrue($service->isLicensed());
        $this->assertCount(2, $service->all());
        $this->assertCount(1, $service->modules());
        $this->assertCount(1, $service->themes());
        $this->assertTrue($service->has('analytics-pro'));
        $this->assertTrue($service->hasModule('analytics-pro'));
        $this->assertTrue($service->hasTheme('dark-panel'));
        $this->assertFalse($service->hasModule('dark-panel'));
        $this->assertFalse($service->has('unknown-sku'));
    }

    public function test_entitlement_service_returns_empty_when_activation_is_not_active(): void
    {
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'suspended',
            'entitlements' => [
                [
                    'product_type' => 'module',
                    'product_sku' => 'analytics-pro',
                    'product_name' => 'Analytics Pro',
                ],
            ],
            'updated_at' => now(),
        ]);

        $service = app(EntitlementService::class);

        $this->assertFalse($service->isLicensed());
        $this->assertSame([], $service->all());
        $this->assertFalse($service->hasModule('analytics-pro'));
    }

    public function test_entitlement_service_ignores_other_instance_activations(): void
    {
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-other',
            'status' => 'active',
            'entitlements' => [
                [
                    'product_type' => 'module',
                    'product_sku' => 'analytics-pro',
                    'product_name' => 'Analytics Pro',
                ],
            ],
            'updated_at' => now(),
        ]);

        $service = app(EntitlementService::class);

        $this->assertFalse($service->isLicensed());
        $this->assertFalse($service->hasModule('analytics-pro'));
    }

    public function test_entitlement_service_matches_skus_case_insensitively(): void
    {
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'status' => 'active',
            'entitlements' => [
                [
                    'product_type' => 'MODULE',
                    'product_sku' => 'Analytics-Pro',
                    'product_name' => 'Analytics Pro',
                ],
            ],
            'updated_at' => now(),
        ]);

        $service = app(EntitlementService::class);

        $this->assertTrue($service->has('analytics-pro'));
        $this->assertTrue($service->hasModule('ANALYTICS-PRO'));
    }
}
