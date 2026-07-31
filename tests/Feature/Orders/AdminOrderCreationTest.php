<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Services\CartService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-order-create',
            'corepanel.billing.tax_preview_rate' => 0.20,
        ]);
    }

    public function test_admin_can_view_create_form_with_client_prefill(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->withRole('client')->create([
            'name' => 'Alice Owner',
            'email' => 'alice@acme.test',
        ]);
        $client = Client::factory()->create([
            'user_id' => $owner->id,
            'company_name' => 'Acme Hosting',
            'address' => '12 Rue Test',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
        ]);
        Product::factory()->published()->withPricing()->create(['name' => 'VPS Admin']);

        $this->actingAs($admin)
            ->get(route('admin.orders.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('Acme Hosting')
            ->assertSee('Alice Owner')
            ->assertSee('alice@acme.test')
            ->assertSee('12 Rue Test')
            ->assertSee('VPS Admin');
    }

    public function test_admin_can_create_draft_order_for_client_with_items(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['company_name' => 'Draft Co']);
        $product = Product::factory()->published()->withPricing([BillingCycle::Monthly], '19.99', '5.00')->create([
            'name' => 'Cloud VPS',
            'slug' => 'cloud-vps-admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), $this->validPayload($client, $product))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame(OrderStatus::Draft, $order->status);
        $this->assertSame(OrderSource::Admin, $order->source);
        $this->assertSame($admin->id, $order->created_by);
        $this->assertSame($client->id, $order->client_id);
        $this->assertNull($order->order_number);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Cloud VPS', $order->items->first()->product_name);
        $this->assertSame('19.99', $order->items->first()->unit_price);
        $this->assertSame('5.00', $order->items->first()->setup_fee);
        $this->assertSame('19.99', $order->subtotal_recurring);
        $this->assertSame('5.00', $order->subtotal_setup);
        $this->assertSame('5.00', $order->tax_amount);
        $this->assertSame('29.99', $order->total_amount);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Cloud VPS')
            ->assertSee(__('Admin'));
    }

    public function test_admin_can_create_and_submit_pending_payment_order(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing()->create();

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), [
                ...$this->validPayload($client, $product),
                'submit_as_pending' => '1',
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNotNull($order->order_number);
        $this->assertNotNull($order->placed_at);
        $this->assertSame($admin->id, $order->created_by);
    }

    public function test_create_does_not_modify_client_open_cart(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing()->create();
        $cartProduct = Product::factory()->published()->withPricing()->create([
            'slug' => 'existing-cart-item',
        ]);

        $cart = app(CartService::class)->getOrCreate($client, null);
        app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $cartProduct->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), $this->validPayload($client, $product))
            ->assertRedirect();

        $cart->refresh();
        $this->assertSame(CartStatus::Open, $cart->status);
        $this->assertSame(1, $cart->items()->count());
        $this->assertSame($cartProduct->id, $cart->items->first()->product_id);
        $this->assertNull(Order::query()->first()?->cart_id);
    }

    public function test_create_requires_at_least_one_line_item(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create();

        $payload = $this->validPayload($client, Product::factory()->published()->withPricing()->create());
        unset($payload['items']);

        $this->actingAs($admin)
            ->from(route('admin.orders.create'))
            ->post(route('admin.orders.store'), $payload)
            ->assertRedirect(route('admin.orders.create'))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_support_can_view_orders_but_cannot_create(): void
    {
        $support = User::factory()->withRole('support')->create();

        $this->actingAs($support)
            ->get(route('admin.orders.create'))
            ->assertForbidden();

        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing()->create();

        $this->actingAs($support)
            ->post(route('admin.orders.store'), $this->validPayload($client, $product))
            ->assertForbidden();
    }

    public function test_client_role_cannot_access_admin_order_create(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->actingAs($clientUser)
            ->get(route('admin.orders.create'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_order_create(): void
    {
        $this->get(route('admin.orders.create'))
            ->assertRedirect(route('login'));
    }

    public function test_draft_admin_order_is_hidden_from_client_history(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $clientUser = User::factory()->withRole('client')->create();
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        $client->users()->attach($clientUser->id, ['role' => ClientMembershipRole::Owner->value]);
        $product = Product::factory()->published()->withPricing()->create();

        $this->actingAs($admin)
            ->post(route('admin.orders.store'), $this->validPayload($client, $product))
            ->assertRedirect();

        $this->actingAs($clientUser)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee(__('No orders yet'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Client $client, Product $product): array
    {
        return [
            'client_id' => $client->id,
            'contact_name' => 'Admin Contact',
            'contact_email' => 'billing@example.test',
            'company_name' => $client->company_name,
            'address' => '1 Admin Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
            'payment_method' => 'manual_transfer',
            'notes' => 'Created by admin',
            'items' => [
                [
                    'product_id' => $product->id,
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'quantity' => 1,
                ],
            ],
        ];
    }
}
