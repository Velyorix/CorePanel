<?php

namespace Tests\Feature\Services;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Events\OrderCreated;
use Core\Orders\Models\Order;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductOptionType;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Core\Products\Models\ProductOption;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Core\Services\Services\ServiceLifecycleService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * End-to-end service lifecycle smoke.
 * Narrower Services Feature tests cover individual slices.
 */
class ServiceLifecycleAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.services-acceptance',
            'session.driver' => 'array',
            'corepanel.billing.tax_preview_rate' => 0.20,
        ]);
    }

    public function test_service_lifecycle_smoke_from_checkout_through_admin_payment_to_client_management(): void
    {
        Event::fake([OrderCreated::class]);

        [$clientUser, $client] = $this->makeClientUser([
            'company_name' => 'Lifecycle Services Co',
            'address' => '10 Rue Services',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
        ]);
        $admin = User::factory()->withRole('admin')->create();
        $product = $this->makeServiceProduct('lifecycle-vps', '24.99', '5.00');

        $this->actingAs($clientUser)
            ->post(route('client.catalog.products.configure.store', $product->slug), [
                'billing_cycle' => BillingCycle::Monthly->value,
                'quantity' => 1,
                'options' => [
                    'hostname' => 'node-lifecycle.example.test',
                ],
            ])
            ->assertRedirect(route('client.cart.index'));

        $this->actingAs($clientUser)
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Alice Client',
                'contact_email' => $clientUser->email,
                'company_name' => 'Lifecycle Services Co',
                'address' => '10 Rue Services',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
            ])
            ->assertRedirect(route('client.checkout.complete'));

        $this->actingAs($clientUser)
            ->post(route('client.checkout.place'))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(0, Service::query()->count());

        Event::assertDispatchedTimes(OrderCreated::class, 1);

        $this->actingAs($admin)
            ->post(route('admin.orders.mark-paid', $order))
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);

        $service = Service::query()->firstOrFail();
        $this->assertSame(ServiceStatus::Pending, $service->status);
        $this->assertSame($client->id, $service->client_id);
        $this->assertSame($order->id, $service->order_id);
        $this->assertSame('pterodactyl', $service->module);
        $this->assertSame('node-lifecycle.example.test', $service->hostname);

        $this->actingAs($admin)
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('node-lifecycle.example.test')
            ->assertSee('Lifecycle Services Co');

        $this->actingAs($admin)
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee(__('Pending'))
            ->assertSee($product->name);

        $this->actingAs($clientUser)
            ->get(route('client.services.index'))
            ->assertOk()
            ->assertSee('node-lifecycle.example.test')
            ->assertSee(__('Pending'));

        $this->actingAs($clientUser)
            ->get(route('client.services.show', $service))
            ->assertOk()
            ->assertSee('node-lifecycle.example.test')
            ->assertSee(__('Pending'));

        $service = $this->activateService($service);
        $this->assertSame(ServiceStatus::Active, $service->status);

        $this->actingAs($admin)
            ->post(route('admin.services.suspend', $service))
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHas('status');

        $service->refresh();
        $this->assertSame(ServiceStatus::Suspended, $service->status);

        $this->actingAs($clientUser)
            ->get(route('client.services.show', $service))
            ->assertOk()
            ->assertSee(__('Suspended'));

        $this->actingAs($admin)
            ->post(route('admin.services.unsuspend', $service))
            ->assertRedirect(route('admin.services.show', $service));

        $service->refresh();
        $this->assertSame(ServiceStatus::Active, $service->status);

        $this->actingAs($clientUser)
            ->post(route('client.services.restart', $service))
            ->assertRedirect(route('client.services.show', $service))
            ->assertSessionHas('status');

        $this->assertTrue(
            ServiceActionLog::query()
                ->where('service_id', $service->id)
                ->where('action', ServiceAction::Restart->value)
                ->whereIn('status', [
                    ServiceActionLogStatus::Success->value,
                    ServiceActionLogStatus::Skipped->value,
                ])
                ->exists(),
        );

        $this->actingAs($admin)
            ->post(route('admin.services.terminate', $service))
            ->assertRedirect(route('admin.services.show', $service));

        $service->refresh();
        $this->assertSame(ServiceStatus::Terminated, $service->status);

        $this->actingAs($clientUser)
            ->get(route('client.services.index'))
            ->assertOk()
            ->assertSee('node-lifecycle.example.test')
            ->assertSee(__('Terminated'));
    }

    public function test_admin_suspended_service_appears_in_client_area_with_suspended_status(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        [$clientUser, $client] = $this->makeClientUser();
        $service = Service::factory()->active()->forClient($client)->create([
            'hostname' => 'suspended-client.example.test',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.services.suspend', $service))
            ->assertRedirect(route('admin.services.show', $service));

        $service->refresh();
        $this->assertSame(ServiceStatus::Suspended, $service->status);

        $this->actingAs($clientUser)
            ->get(route('client.services.index'))
            ->assertOk()
            ->assertSee('suspended-client.example.test')
            ->assertSee(__('Suspended'));

        $this->actingAs($clientUser)
            ->get(route('client.services.show', $service))
            ->assertOk()
            ->assertSee(__('Suspended'))
            ->assertDontSee(route('client.services.restart', $service), false);
    }

    public function test_http_guards_prevent_invalid_service_control_actions(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $support = User::factory()->withRole('support')->create();
        [$clientUser, $client] = $this->makeClientUser();
        $service = Service::factory()->suspended()->forClient($client)->create([
            'hostname' => 'guarded.example.test',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.services.show', $service))
            ->post(route('admin.services.start', $service))
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHasErrors('action');

        $this->assertSame(ServiceStatus::Suspended, $service->fresh()->status);

        $this->actingAs($clientUser)
            ->from(route('client.services.show', $service))
            ->post(route('client.services.start', $service))
            ->assertRedirect(route('client.services.show', $service))
            ->assertSessionHasErrors('action');

        $this->actingAs($support)
            ->post(route('admin.services.suspend', $service))
            ->assertForbidden();

        $terminated = Service::factory()->terminated()->forClient($client)->create();

        $this->actingAs($admin)
            ->from(route('admin.services.show', $terminated))
            ->post(route('admin.services.suspend', $terminated))
            ->assertRedirect(route('admin.services.show', $terminated))
            ->assertSessionHasErrors('action');
    }

    /**
     * @param  array<string, mixed>  $clientAttributes
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(array $clientAttributes = []): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            ...$clientAttributes,
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }

    private function makeServiceProduct(
        string $slug,
        string $price = '10.00',
        string $setupFee = '0.00',
    ): Product {
        $category = ProductCategory::factory()->create([
            'slug' => 'service-lifecycle-cat-'.fake()->unique()->numerify('###'),
        ]);

        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            $price,
            $setupFee,
        )->withModule('pterodactyl')->create([
            'category_id' => $category->id,
            'name' => 'Lifecycle '.$slug,
            'slug' => $slug,
            'type' => ProductType::Vps,
            'status' => ProductStatus::Published,
        ]);

        ProductOption::factory()->required()->create([
            'product_id' => $product->id,
            'key' => 'hostname',
            'name' => 'Hostname',
            'type' => ProductOptionType::Text,
            'config' => ['max_length' => 64, 'format' => 'hostname'],
            'sort_order' => 1,
        ]);

        return $product->fresh(['options', 'pricing', 'category']);
    }

    /**
     * Simulates module provisioning completion until provider automation exists.
     */
    private function activateService(Service $service): Service
    {
        $lifecycle = app(ServiceLifecycleService::class);

        $provisioning = $lifecycle->markProvisioning($service);

        return $lifecycle->markActive($provisioning);
    }
}
