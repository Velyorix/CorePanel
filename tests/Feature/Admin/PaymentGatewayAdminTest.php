<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\PaymentGateway;
use Core\Billing\Services\GatewayManager;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class PaymentGatewayAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-gateways',
            'app.key' => 'base64:'.base64_encode(str_repeat('d', 32)),
        ]);
    }

    public function test_admin_can_list_registered_gateways(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.gateways.index'))
            ->assertOk()
            ->assertSee(ManualTransferGateway::KEY)
            ->assertSee(__('Payment gateways'));
    }

    public function test_admin_can_enable_disable_and_configure_gateway(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $gateways = app(GatewayManager::class);
        $gateways->register(new FakePaymentGateway);
        $gateways->sync();
        $gateways->disable('fake');

        $this->actingAs($admin)
            ->post(route('admin.gateways.enable', 'fake'))
            ->assertRedirect(route('admin.gateways.show', 'fake'));

        $this->assertTrue($gateways->isEnabled('fake'));

        $this->actingAs($admin)
            ->put(route('admin.gateways.config', 'fake'), [
                'config' => '{"api_key":"sk_test","mode":"test"}',
            ])
            ->assertRedirect(route('admin.gateways.show', 'fake'));

        $this->assertSame(
            ['api_key' => 'sk_test', 'mode' => 'test'],
            $gateways->record('fake')?->config,
        );

        $this->actingAs($admin)
            ->get(route('admin.gateways.show', 'fake'))
            ->assertOk()
            ->assertSee('sk_test')
            ->assertSee(__('Save configuration'));

        $this->actingAs($admin)
            ->post(route('admin.gateways.disable', 'fake'))
            ->assertRedirect(route('admin.gateways.show', 'fake'));

        $this->assertFalse($gateways->isEnabled('fake'));
    }

    public function test_admin_can_reorder_gateways(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $gateways = app(GatewayManager::class);
        $gateways->register(new FakePaymentGateway(key: 'alpha'));
        $gateways->register(new FakePaymentGateway(key: 'beta'));
        $gateways->sync();
        $gateways->reorder(['alpha', 'beta', ManualTransferGateway::KEY]);

        $this->assertSame(
            ['alpha', 'beta', ManualTransferGateway::KEY],
            array_values(array_intersect($gateways->orderedKeys(), ['alpha', 'beta', ManualTransferGateway::KEY])),
        );

        $this->actingAs($admin)
            ->post(route('admin.gateways.move-down', 'alpha'))
            ->assertRedirect(route('admin.gateways.index'));

        $this->assertSame(2, (int) PaymentGateway::query()->where('key', 'alpha')->value('sort_order'));
        $this->assertSame(1, (int) PaymentGateway::query()->where('key', 'beta')->value('sort_order'));
    }

    public function test_invalid_config_json_is_rejected(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        app(GatewayManager::class)->sync();

        $this->actingAs($admin)
            ->from(route('admin.gateways.show', ManualTransferGateway::KEY))
            ->put(route('admin.gateways.config', ManualTransferGateway::KEY), [
                'config' => '{bad',
            ])
            ->assertRedirect(route('admin.gateways.show', ManualTransferGateway::KEY))
            ->assertSessionHasErrors('config');
    }

    public function test_user_without_settings_view_cannot_access_gateways(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.gateways.index'))
            ->assertForbidden();
    }

    public function test_settings_view_without_manage_cannot_mutate(): void
    {
        $viewer = $this->makeSettingsViewer();
        app(GatewayManager::class)->sync();

        $this->actingAs($viewer)
            ->get(route('admin.gateways.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('admin.gateways.disable', ManualTransferGateway::KEY))
            ->assertForbidden();
    }

    public function test_navigation_links_payment_gateways(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $item = collect(app(\Core\Admin\Navigation\AdminNavigation::class)->forUser($admin))
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('label', __('Payment gateways'));

        $this->assertNotNull($item);
        $this->assertFalse($item['placeholder']);
        $this->assertSame(route('admin.gateways.index'), $item['url']);
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
