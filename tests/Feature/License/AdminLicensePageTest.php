<?php

namespace Tests\Feature\License;

use App\Models\User;
use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminLicensePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'cache.default' => 'array',
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-license',
            'corepanel.instance.label' => 'Production',
            'corepanel.instance.domain' => 'cms.example.com',
        ]);
    }

    public function test_admin_can_view_license_status_page_with_masked_key_and_entitlements(): void
    {
        $this->configureValidLicense();

        app(LicenseSettings::class)->setLicenseKey('CP-LIVE-AAAA-BBBBCCCCDDDD');

        LicenseActivation::query()->updateOrCreate(
            ['instance_id' => '550e8400-e29b-41d4-a716-446655440000'],
            [
                'status' => 'active',
                'product_type' => 'account',
                'last_validated_at' => now(),
                'entitlements' => [
                    [
                        'product_type' => 'module',
                        'product_sku' => 'analytics-pro',
                        'product_name' => 'Analytics Pro',
                        'granted_at' => '2026-06-01T08:00:00+00:00',
                    ],
                ],
                'updated_at' => now(),
            ],
        );

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.license.show'))
            ->assertOk()
            ->assertSee(__('License status'), false)
            ->assertSee(__('Entitlements'), false)
            ->assertSee('Analytics Pro', false)
            ->assertSee('analytics-pro', false)
            ->assertSee(app(LicenseSettings::class)->maskedLicenseKey(), false)
            ->assertDontSee('CP-LIVE-AAAA-BBBBCCCCDDDD', false)
            ->assertSee(__('Update license key'), false)
            ->assertSee(route('admin.license.revalidate'), false);
    }

    public function test_support_user_cannot_view_license_page(): void
    {
        $this->configureValidLicense();

        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.license.show'))
            ->assertForbidden();
    }

    public function test_admin_can_update_license_key_without_changing_instance_id(): void
    {
        $this->configureValidLicense();

        $settings = app(LicenseSettings::class);
        $originalInstanceId = $settings->instanceId();

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => true,
                'license' => [
                    'status' => 'active',
                    'product_type' => 'account',
                ],
                'activation' => [
                    'status' => 'active',
                ],
                'entitlements' => [
                    [
                        'product_type' => 'theme',
                        'product_sku' => 'dark-panel',
                        'product_name' => 'Dark Panel',
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.license.show'))
            ->put(route('admin.license.update'), [
                'license_key' => 'CP-NEW-KEY-1234567890',
            ])
            ->assertRedirect(route('admin.license.show'))
            ->assertSessionHas('status');

        $this->assertSame('CP-NEW-KEY-1234567890', $settings->licenseKey());
        $this->assertSame($originalInstanceId, $settings->instanceId());
    }

    public function test_admin_can_force_revalidate_license(): void
    {
        $this->configureValidLicense();

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => true,
                'license' => [
                    'status' => 'active',
                ],
                'activation' => [],
                'entitlements' => [],
            ], 200),
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.license.show'))
            ->post(route('admin.license.revalidate'))
            ->assertRedirect(route('admin.license.show'))
            ->assertSessionHas('status');

        Http::assertSentCount(1);
    }

    public function test_license_page_remains_accessible_when_license_is_invalid(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-BAD-KEY');
        app(LicenseSettings::class)->setInstanceId('550e8400-e29b-41d4-a716-446655440000');

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => false,
                'reason' => 'invalid_key',
                'message' => 'License key is invalid.',
            ], 403),
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.license.show'))
            ->assertOk()
            ->assertSee(__('Update license key'), false);
    }

    public function test_navigation_includes_license_settings_item_for_admin(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('License'), false)
            ->assertSee(route('admin.license.show'), false);
    }
}
