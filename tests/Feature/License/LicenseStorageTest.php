<?php

namespace Tests\Feature\License;

use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Core\Settings\Models\Setting;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LicenseStorageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configureValidLicenseByDefault = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_license_activations_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('license_activations'));

        foreach ([
            'instance_id',
            'license_remote_id',
            'status',
            'instance_label',
            'domain',
            'product_type',
            'expires_at',
            'last_validated_at',
            'last_seen_at',
            'entitlements',
            'metadata',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('license_activations', $column),
                "Expected column [{$column}] on [license_activations].",
            );
        }
    }

    public function test_license_settings_encrypts_license_key_and_instance_id(): void
    {
        $settings = app(LicenseSettings::class);

        $settings->setLicenseKey('CP-TEST-1234567890');
        $settings->setInstanceId('cms-a1b2c3d4-e5f6-7890-abcd-ef1234567890');

        $licenseSetting = Setting::query()->where('key', 'license.key')->firstOrFail();
        $instanceSetting = Setting::query()->where('key', 'license.instance_id')->firstOrFail();

        $this->assertSame('encrypted', $licenseSetting->type);
        $this->assertSame('encrypted', $instanceSetting->type);
        $this->assertNotSame('CP-TEST-1234567890', $licenseSetting->value);
        $this->assertNotSame('cms-a1b2c3d4-e5f6-7890-abcd-ef1234567890', $instanceSetting->value);
        $this->assertSame('CP-TEST-1234567890', $settings->licenseKey());
        $this->assertSame('cms-a1b2c3d4-e5f6-7890-abcd-ef1234567890', $settings->instanceId());
        $this->assertTrue($settings->hasLicenseKey());
        $this->assertTrue($settings->hasInstanceId());
        $this->assertNotSame('CP-TEST-1234567890', $settings->maskedLicenseKey());
        $this->assertStringStartsWith('CP-TEST-', (string) $settings->maskedLicenseKey());
        $this->assertStringContainsString('•', (string) $settings->maskedLicenseKey());
    }

    public function test_license_settings_returns_null_for_missing_or_invalid_encrypted_values(): void
    {
        $settings = app(LicenseSettings::class);

        $this->assertNull($settings->licenseKey());
        $this->assertNull($settings->instanceId());

        Setting::query()->create([
            'key' => 'license.key',
            'value' => 'not-encrypted',
            'type' => 'encrypted',
            'autoload' => false,
            'updated_at' => now(),
        ]);

        $this->assertNull($settings->licenseKey());
    }

    public function test_license_activation_model_casts_json_and_dates(): void
    {
        $activation = LicenseActivation::query()->create([
            'instance_id' => 'cms-prod-01',
            'license_remote_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'active',
            'instance_label' => 'Production',
            'domain' => 'cms.example.com',
            'product_type' => 'account',
            'expires_at' => now()->addMonth(),
            'last_validated_at' => now(),
            'last_seen_at' => now(),
            'entitlements' => [
                ['product_sku' => 'analytics-pro'],
            ],
            'metadata' => [
                'cms_version' => '0.0.0-dev',
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertIsArray($activation->entitlements);
        $this->assertIsArray($activation->metadata);
        $this->assertNotNull($activation->expires_at);
        $this->assertSame('analytics-pro', $activation->entitlements[0]['product_sku']);
    }
}

