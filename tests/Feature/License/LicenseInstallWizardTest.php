<?php

namespace Tests\Feature\License;

use Core\License\Services\LicenseSettings;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicenseInstallWizardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $configureValidLicenseByDefault = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'cache.default' => 'array',
            'corepanel.instance.label' => 'Production',
            'corepanel.instance.domain' => 'cms.example.com',
        ]);
    }

    public function test_guest_can_view_license_install_wizard(): void
    {
        $this->get(route('install.license.create'))
            ->assertOk()
            ->assertSee('Activate your license to finish installation');
    }

    public function test_install_wizard_generates_instance_uuid_and_activates_license(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => true,
                'license' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'status' => 'active',
                    'product_type' => 'account',
                ],
                'activation' => [
                    'instance_id' => 'generated-by-cms',
                    'status' => 'active',
                ],
                'entitlements' => [],
            ], 200),
        ]);

        $response = $this->post(route('install.license.store'), [
            'license_key' => 'CP-LIVE-AAAA-BBBB',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'License activated successfully.');

        $settings = app(LicenseSettings::class);
        $this->assertSame('CP-LIVE-AAAA-BBBB', $settings->licenseKey());
        $this->assertNotNull($settings->instanceId());
        $this->assertTrue(Str::isUuid((string) $settings->instanceId()));
    }

    public function test_install_wizard_reuses_existing_instance_id_when_present(): void
    {
        app(LicenseSettings::class)->setInstanceId('550e8400-e29b-41d4-a716-446655440000');

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

        $this->post(route('install.license.store'), [
            'license_key' => 'CP-LIVE-AAAA-BBBB',
        ])->assertRedirect(route('login'));

        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            app(LicenseSettings::class)->instanceId(),
        );
    }

    public function test_install_wizard_returns_error_on_invalid_license(): void
    {
        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => false,
                'reason' => 'invalid_license_key',
                'message' => 'License key is invalid.',
                'license' => [
                    'status' => 'invalid',
                ],
            ], 422),
        ]);

        $response = $this->from(route('install.license.create'))
            ->post(route('install.license.store'), [
                'license_key' => 'CP-BAD-KEY',
            ]);

        $response->assertRedirect(route('install.license.create'));
        $response->assertSessionHasErrors('license_key');
    }

    public function test_authenticated_super_admin_can_open_install_wizard_when_license_is_missing(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.install-wizard',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('install.license.create'));

        $this->actingAs($superAdmin)
            ->get(route('install.license.create'))
            ->assertOk()
            ->assertSee('Activate your license to finish installation');
    }

    public function test_authenticated_admin_is_redirected_to_admin_after_license_activation(): void
    {
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

        $superAdmin = User::factory()->withRole('super-admin')->create();

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.install-wizard',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('install.license.store'), [
                'license_key' => 'CP-LIVE-AAAA-BBBB',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
