<?php

namespace Tests\Feature\License;

use App\Http\Middleware\EnsureValidLicense;
use App\Models\User;
use Core\License\Models\LicenseActivation;
use Core\License\Services\LicenseSettings;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureValidLicenseMiddlewareTest extends TestCase
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
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.license-middleware',
        ]);

        Route::middleware('license.valid')
            ->get('/license-test/protected', fn () => response('licensed-content'))
            ->name('license.test.protected');
    }

    public function test_admin_and_client_middleware_groups_include_license_validation(): void
    {
        $adminRoute = Route::getRoutes()->getByName('admin.dashboard');
        $clientRoute = Route::getRoutes()->getByName('client.dashboard');

        $this->assertNotNull($adminRoute);
        $this->assertNotNull($clientRoute);
        $this->assertContains('admin', $adminRoute->gatherMiddleware());
        $this->assertContains('client', $clientRoute->gatherMiddleware());
    }

    public function test_missing_license_configuration_redirects_to_install_wizard(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('install.license.create'));

        $this->actingAs($admin)
            ->get(route('install.license.create'))
            ->assertOk();
    }

    public function test_valid_license_allows_admin_access(): void
    {
        $this->configureValidLicense();

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_grace_period_allows_access_in_degraded_mode(): void
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

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('licenseDegraded', true);
    }

    public function test_invalid_license_returns_degraded_page(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-BAD-KEY');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => false,
                'reason' => 'invalid_key',
                'message' => 'License key is invalid.',
                'license' => [
                    'status' => 'invalid',
                ],
            ], 422),
        ]);

        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertStatus(503)
            ->assertSee('License key is invalid.');
    }

    public function test_install_wizard_routes_remain_accessible_without_license(): void
    {
        $this->get(route('install.license.create'))
            ->assertOk()
            ->assertSee('Activate your license to finish installation');
    }

    public function test_license_middleware_returns_json_error_for_api_style_requests(): void
    {
        app(LicenseSettings::class)->setLicenseKey('CP-BAD-KEY');
        app(LicenseSettings::class)->setInstanceId('cms-prod-01');

        Http::fake([
            'https://corepanel.org/api/v1/licenses/validate' => Http::response([
                'valid' => false,
                'reason' => 'invalid_key',
                'message' => 'License key is invalid.',
                'license' => [
                    'status' => 'invalid',
                ],
            ], 422),
        ]);

        $this->getJson('/license-test/protected')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'license_invalid');
    }
}
