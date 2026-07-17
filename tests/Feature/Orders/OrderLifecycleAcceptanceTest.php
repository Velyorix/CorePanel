<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Events\OrderCancelled;
use Core\Orders\Events\OrderCreated;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\Order;
use Core\Orders\Services\CartService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductCategory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * End-to-end order lifecycle smoke (roadmap 11.8).
 * Slice coverage for 11.1–11.7 lives in dedicated Feature tests.
 */
class OrderLifecycleAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.orders-acceptance',
            'session.driver' => 'array',
            'corepanel.billing.tax_preview_rate' => 0.20,
        ]);
    }

    public function test_order_lifecycle_smoke_from_checkout_through_admin_payment_to_client_history(): void
    {
        Event::fake([OrderCreated::class, OrderPaid::class, OrderCancelled::class]);

        [$clientUser, $client] = $this->makeClientUser([
            'company_name' => 'Lifecycle Co',
            'address' => '10 Rue Lifecycle',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'FR',
        ]);
        $admin = User::factory()->withRole('admin')->create();
        $product = $this->makeProduct('lifecycle-vps', '19.99', '5.00');

        $this->addLine($client, $product, 1);

        $this->actingAs($clientUser)
            ->post(route('client.checkout.store'), [
                'contact_name' => 'Alice Client',
                'contact_email' => $clientUser->email,
                'company_name' => 'Lifecycle Co',
                'address' => '10 Rue Lifecycle',
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
        $this->assertSame($client->id, $order->client_id);
        $this->assertSame('19.99', $order->subtotal_recurring);
        $this->assertSame('5.00', $order->subtotal_setup);
        $this->assertCount(1, $order->items);
        $this->assertSame($product->id, $order->items->first()->product_id);

        Event::assertDispatchedTimes(OrderCreated::class, 1);
        Event::assertDispatched(
            OrderCreated::class,
            fn (OrderCreated $event): bool => $event->order->is($order),
        );

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Lifecycle Co');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(__('Pending payment'))
            ->assertSee($product->name);

        $this->actingAs($clientUser)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(__('Pending payment'));

        $this->actingAs($admin)
            ->post(route('admin.orders.mark-paid', $order))
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);

        Event::assertDispatchedTimes(OrderPaid::class, 1);
        Event::assertDispatched(
            OrderPaid::class,
            fn (OrderPaid $event): bool => $event->order->is($order),
        );
        Event::assertNotDispatched(OrderCancelled::class);

        $this->actingAs($clientUser)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(__('Paid'))
            ->assertSee(__('Payment received'))
            ->assertSee($product->name);
    }

    public function test_admin_pending_order_is_visible_in_client_history(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        [$clientUser, $client] = $this->makeClientUser();
        $product = $this->makeProduct('admin-pending-vps');

        $response = $this->actingAs($admin)
            ->post(route('admin.orders.store'), [
                'client_id' => $client->id,
                'contact_name' => 'Alice Client',
                'contact_email' => $clientUser->email,
                'company_name' => $client->company_name,
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
                'submit_as_pending' => '1',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'billing_cycle' => BillingCycle::Monthly->value,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $order = Order::query()->firstOrFail();

        $response
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status');

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNotNull($order->order_number);

        $this->actingAs($clientUser)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(__('Pending payment'));

        $this->actingAs($clientUser)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee(__('Order placed'));
    }

    public function test_admin_cancelled_order_appears_in_client_history_with_cancelled_status(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        [$clientUser, $client] = $this->makeClientUser();
        $order = Order::factory()->pendingPayment()->forClient($client)->create([
            'order_number' => 'ORD-CANCEL-CLIENT',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.cancel', $order), [
                'reason' => 'Duplicate order',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);

        $this->actingAs($clientUser)
            ->get(route('client.orders.index'))
            ->assertOk()
            ->assertSee('ORD-CANCEL-CLIENT')
            ->assertSee(__('Cancelled'));

        $this->actingAs($clientUser)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee(__('Cancelled'))
            ->assertSee(__('Order placed'));
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

    private function makeProduct(
        string $slug,
        string $price = '10.00',
        string $setupFee = '0.00',
    ): Product {
        $category = ProductCategory::factory()->create([
            'slug' => 'lifecycle-cat-'.fake()->unique()->numerify('###'),
        ]);

        return Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            $price,
            $setupFee,
        )->create([
            'category_id' => $category->id,
            'name' => 'Lifecycle '.$slug,
            'slug' => $slug,
            'type' => ProductType::Other,
            'status' => ProductStatus::Published,
        ]);
    }

    private function addLine(Client $client, Product $product, int $quantity): void
    {
        $cart = app(CartService::class)->getOrCreate($client, null);
        app(CartService::class)->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => $quantity,
        ]));
    }
}
