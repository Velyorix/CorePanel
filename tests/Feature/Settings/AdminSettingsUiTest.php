<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Core\Admin\Navigation\AdminNavigation;
use Core\Billing\Services\BillingSettings;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Settings\Services\SettingsService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsUiTest extends TestCase
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
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-settings',
            'corepanel.settings.cache.enabled' => true,
            'corepanel.settings.cache.store' => 'array',
            'corepanel.settings.cache.prefix' => 'test.settings.admin-ui',
            'corepanel.locale.supported' => ['en', 'fr'],
        ]);

        app(SettingsService::class)->flushCache();
    }

    public function test_admin_can_view_and_update_general_settings(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.general'))
            ->assertOk()
            ->assertSee(__('General settings'), false);

        $this->actingAs($admin)
            ->from(route('admin.settings.general'))
            ->put(route('admin.settings.general.update'), [
                'site_name' => 'Acme Panel',
                'locale' => 'fr',
                'timezone' => 'Europe/Paris',
            ])
            ->assertRedirect(route('admin.settings.general'))
            ->assertSessionHas('status');

        $settings = app(SettingsService::class);

        $this->assertSame('Acme Panel', $settings->getString('general.site_name'));
        $this->assertSame('fr', $settings->getString('general.locale'));
        $this->assertSame('Europe/Paris', $settings->getString('general.timezone'));
    }

    public function test_admin_can_update_billing_settings_and_see_gateways_link(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.billing'))
            ->assertOk()
            ->assertSee(__('Manage payment gateways'), false)
            ->assertSee(route('admin.gateways.index'), false);

        $this->actingAs($admin)
            ->from(route('admin.settings.billing'))
            ->put(route('admin.settings.billing.update'), [
                'invoice_prefix' => 'FAC',
                'quote_prefix' => 'DEV',
                'credit_note_prefix' => 'AVO',
                'default_currency' => 'usd',
                'tax_preview_rate' => '20',
                'renewal_enabled' => '1',
            ])
            ->assertRedirect(route('admin.settings.billing'))
            ->assertSessionHas('status');

        $billing = app(BillingSettings::class);
        $settings = app(SettingsService::class);

        $this->assertSame('FAC', $billing->invoicePrefix());
        $this->assertSame('DEV', $billing->quotePrefix());
        $this->assertSame('AVO', $billing->creditNotePrefix());
        $this->assertSame('USD', $settings->getString('billing.default_currency'));
        $this->assertSame('20', $settings->getString('billing.tax_preview_rate'));
        $this->assertTrue($settings->getBool('billing.renewal_enabled'));
    }

    public function test_mail_password_is_encrypted_and_never_shown_in_clear(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $secret = 'smtp-super-secret';

        $this->actingAs($admin)
            ->from(route('admin.settings.mail'))
            ->put(route('admin.settings.mail.update'), [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'mailer',
                'password' => $secret,
                'encryption' => 'tls',
                'from_address' => 'noreply@example.com',
                'from_name' => 'CorePanel',
            ])
            ->assertRedirect(route('admin.settings.mail'));

        $settings = app(SettingsService::class);

        $this->assertSame($secret, $settings->getEncrypted('mail.password'));
        $this->assertSame('smtp.example.com', $settings->getString('mail.host'));

        $this->actingAs($admin)
            ->get(route('admin.settings.mail'))
            ->assertOk()
            ->assertDontSee($secret, false)
            ->assertSee(__('Leave blank to keep the current password.'), false);
    }

    public function test_admin_can_update_security_settings(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.settings.security'))
            ->put(route('admin.settings.security.update'), [
                'two_factor_required' => '1',
                'password_min_length' => 16,
                'password_require_special' => '1',
                'password_check_compromised' => '1',
                'session_timeout_minutes' => 45,
            ])
            ->assertRedirect(route('admin.settings.security'))
            ->assertSessionHas('status');

        $settings = app(SettingsService::class);

        $this->assertTrue($settings->getBool('security.two_factor_required'));
        $this->assertSame(16, $settings->getInt('security.password_min_length'));
        $this->assertTrue($settings->getBool('security.password_require_special'));
        $this->assertTrue($settings->getBool('security.password_check_compromised'));
        $this->assertSame(45, $settings->getInt('security.session_timeout_minutes'));
    }

    public function test_viewer_can_read_but_cannot_update_settings(): void
    {
        $viewer = $this->makeSettingsViewer();

        $this->actingAs($viewer)
            ->get(route('admin.settings.general'))
            ->assertOk()
            ->assertDontSee(__('Save settings'), false);

        $this->actingAs($viewer)
            ->put(route('admin.settings.general.update'), [
                'site_name' => 'Hacked',
                'locale' => 'en',
                'timezone' => 'UTC',
            ])
            ->assertForbidden();

        $this->assertNull(app(SettingsService::class)->getString('general.site_name'));
    }

    public function test_support_user_cannot_view_settings_pages(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.settings.mail'))
            ->assertForbidden();
    }

    public function test_navigation_wires_settings_sections(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $items = collect(app(AdminNavigation::class)->forUser($admin))
            ->flatMap(fn (array $section) => $section['items']);

        foreach ([
            __('General') => 'admin.settings.general',
            __('Billing') => 'admin.settings.billing',
            __('Security') => 'admin.settings.security',
            __('Mail') => 'admin.settings.mail',
        ] as $label => $routeName) {
            $item = $items->firstWhere('label', $label);

            $this->assertNotNull($item, "Missing nav item: {$label}");
            $this->assertFalse($item['placeholder']);
            $this->assertSame(route($routeName), $item['url']);
        }
    }

    private function makeSettingsViewer(): User
    {
        $role = Role::query()->create([
            'name' => 'settings-viewer',
            'description' => 'Can view settings but not manage them',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', ['admin.access', 'settings.view'])
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
