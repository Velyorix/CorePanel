<?php

namespace Tests\Feature\Orders;

use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Events\OrderCancelled;
use Core\Orders\Events\OrderCreated;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\Order;
use Core\Orders\Services\CartService;
use Core\Orders\Services\OrderConversionService;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);
    }

    public function test_order_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(OrderService::class),
            app(OrderService::class),
        );
    }

    public function test_status_allowed_transitions_match_lifecycle_graph(): void
    {
        $this->assertSame(
            [OrderStatus::PendingPayment, OrderStatus::Cancelled],
            OrderStatus::Draft->allowedTransitions(),
        );
        $this->assertSame(
            [OrderStatus::Paid, OrderStatus::Cancelled],
            OrderStatus::PendingPayment->allowedTransitions(),
        );
        $this->assertSame([], OrderStatus::Paid->allowedTransitions());
        $this->assertSame([], OrderStatus::Cancelled->allowedTransitions());

        $this->assertTrue(OrderStatus::Draft->canTransitionTo(OrderStatus::PendingPayment));
        $this->assertFalse(OrderStatus::Draft->canTransitionTo(OrderStatus::Paid));
        $this->assertFalse(OrderStatus::Paid->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_create_draft_order_for_client(): void
    {
        $client = Client::factory()->create();

        $order = $this->orderService->createDraft($client, [
            'currency' => 'EUR',
            'contact_name' => 'Alice',
            'contact_email' => 'alice@example.test',
            'notes' => 'Admin draft',
        ]);

        $this->assertSame(OrderStatus::Draft, $order->status);
        $this->assertSame(OrderSource::Admin, $order->source);
        $this->assertSame($client->id, $order->client_id);
        $this->assertNull($order->order_number);
        $this->assertNull($order->placed_at);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->cancelled_at);
        $this->assertSame('Admin draft', $order->notes);
    }

    public function test_draft_to_pending_payment_to_paid_lifecycle(): void
    {
        $order = $this->orderService->createDraft(Client::factory()->create());

        $pending = $this->orderService->markPendingPayment($order);

        $this->assertSame(OrderStatus::PendingPayment, $pending->status);
        $this->assertNotNull($pending->placed_at);
        $this->assertSame(
            sprintf('ORD-%s-%06d', $pending->created_at->format('Ymd'), $pending->id),
            $pending->order_number,
        );

        $paid = $this->orderService->markPaid($pending);

        $this->assertSame(OrderStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertNull($paid->cancelled_at);
    }

    public function test_draft_can_be_cancelled(): void
    {
        $order = $this->orderService->createDraft(Client::factory()->create());

        $cancelled = $this->orderService->cancel($order, 'Client abandoned');

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Client abandoned', $cancelled->notes);
    }

    public function test_pending_payment_can_be_cancelled(): void
    {
        $order = $this->orderService->markPendingPayment(
            $this->orderService->createDraft(Client::factory()->create()),
        );

        $cancelled = $this->orderService->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertNotNull($cancelled->placed_at);
        $this->assertNull($cancelled->paid_at);
    }

    public function test_transitions_are_idempotent(): void
    {
        $order = $this->orderService->markPendingPayment(
            $this->orderService->createDraft(Client::factory()->create()),
        );
        $placedAt = $order->placed_at?->toISOString();

        $again = $this->orderService->markPendingPayment($order);
        $this->assertSame($placedAt, $again->placed_at?->toISOString());

        $paid = $this->orderService->markPaid($again);
        $paidAt = $paid->paid_at?->toISOString();
        $paidAgain = $this->orderService->markPaid($paid);
        $this->assertSame($paidAt, $paidAgain->paid_at?->toISOString());

        $cancelled = $this->orderService->cancel(
            $this->orderService->createDraft(Client::factory()->create()),
        );
        $cancelledAt = $cancelled->cancelled_at?->toISOString();
        $cancelledAgain = $this->orderService->cancel($cancelled);
        $this->assertSame($cancelledAt, $cancelledAgain->cancelled_at?->toISOString());
    }

    public function test_cannot_mark_draft_as_paid_directly(): void
    {
        $order = $this->orderService->createDraft(Client::factory()->create());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only pending payment orders can be marked paid.');

        $this->orderService->markPaid($order);
    }

    public function test_cannot_cancel_paid_order(): void
    {
        $order = $this->orderService->markPaid(
            $this->orderService->markPendingPayment(
                $this->orderService->createDraft(Client::factory()->create()),
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This order cannot be cancelled from its current status.');

        $this->orderService->cancel($order);
    }

    public function test_cannot_reopen_cancelled_order_via_pending_payment(): void
    {
        $order = $this->orderService->cancel(
            $this->orderService->createDraft(Client::factory()->create()),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only draft orders can be marked pending payment.');

        $this->orderService->markPendingPayment($order);
    }

    public function test_factory_orders_can_be_driven_through_service(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Draft,
            'source' => OrderSource::ClientCheckout,
        ]);

        $pending = $this->orderService->markPendingPayment($order);
        $this->assertSame(OrderStatus::PendingPayment, $pending->status);

        $paid = $this->orderService->markPaid($pending);
        $this->assertSame(OrderStatus::Paid, $paid->status);
    }

    public function test_create_from_checkout_builds_pending_payment_order_via_state_machine(): void
    {
        config(['corepanel.billing.tax_preview_rate' => 0.20]);

        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing(
            [BillingCycle::Monthly],
            '10.00',
            '2.00',
        )->create(['slug' => 'order-service-checkout']);

        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreate($client, null);
        $cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
            'quantity' => 1,
        ]));

        $draft = CheckoutDraftData::fromArray([
            'contact_name' => 'Checkout User',
            'contact_email' => 'checkout@example.test',
            'address' => '9 Rue Test',
            'city' => 'Lille',
            'postal_code' => '59000',
            'country' => 'FR',
            'payment_method' => 'manual_transfer',
        ]);

        $order = $this->orderService->createFromCheckout(
            $cart->fresh(['items.product', 'client']),
            $draft,
        );

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(OrderSource::ClientCheckout, $order->source);
        $this->assertSame($cart->id, $order->cart_id);
        $this->assertNotNull($order->order_number);
        $this->assertNotNull($order->placed_at);
        $this->assertSame('10.00', $order->subtotal_recurring);
        $this->assertSame('2.00', $order->subtotal_setup);
        $this->assertSame('2.40', $order->tax_amount);
        $this->assertSame('14.40', $order->total_amount);
        $this->assertCount(1, $order->items);
        $this->assertSame($product->name, $order->items->first()->product_name);
        $this->assertTrue($cart->fresh()->status === CartStatus::Converted);
    }

    public function test_conversion_facade_delegates_to_order_service(): void
    {
        config(['corepanel.billing.tax_preview_rate' => 0]);

        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing()->create([
            'slug' => 'facade-checkout',
        ]);

        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreate($client, null);
        $cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $order = app(OrderConversionService::class)->convertFromCheckout(
            $cart->fresh(['items.product', 'client']),
            CheckoutDraftData::fromArray([
                'contact_name' => 'Facade User',
                'contact_email' => 'facade@example.test',
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
            ]),
        );

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(OrderSource::ClientCheckout, $order->source);
    }

    public function test_create_draft_does_not_dispatch_lifecycle_events(): void
    {
        Event::fake([OrderCreated::class, OrderPaid::class, OrderCancelled::class]);

        $this->orderService->createDraft(Client::factory()->create());

        Event::assertNotDispatched(OrderCreated::class);
        Event::assertNotDispatched(OrderPaid::class);
        Event::assertNotDispatched(OrderCancelled::class);
    }

    public function test_mark_pending_payment_dispatches_order_created_once(): void
    {
        Event::fake([OrderCreated::class]);

        $order = $this->orderService->markPendingPayment(
            $this->orderService->createDraft(Client::factory()->create()),
        );

        Event::assertDispatched(
            OrderCreated::class,
            fn (OrderCreated $event): bool => $event->order->is($order),
        );
        Event::assertDispatchedTimes(OrderCreated::class, 1);

        $this->orderService->markPendingPayment($order->fresh() ?? $order);
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_mark_paid_dispatches_order_paid_once(): void
    {
        Event::fake([OrderPaid::class]);

        $order = $this->orderService->markPaid(
            $this->orderService->markPendingPayment(
                $this->orderService->createDraft(Client::factory()->create()),
            ),
        );

        Event::assertDispatched(
            OrderPaid::class,
            fn (OrderPaid $event): bool => $event->order->is($order),
        );
        Event::assertDispatchedTimes(OrderPaid::class, 1);

        $this->orderService->markPaid($order->fresh() ?? $order);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_cancel_dispatches_order_cancelled_without_order_created_when_from_draft(): void
    {
        Event::fake([OrderCreated::class, OrderCancelled::class]);

        $order = $this->orderService->cancel(
            $this->orderService->createDraft(Client::factory()->create()),
            'Abandoned draft',
        );

        Event::assertNotDispatched(OrderCreated::class);
        Event::assertDispatched(
            OrderCancelled::class,
            fn (OrderCancelled $event): bool => $event->order->is($order),
        );
        Event::assertDispatchedTimes(OrderCancelled::class, 1);
    }

    public function test_cancel_pending_payment_dispatches_order_cancelled_once(): void
    {
        Event::fake([OrderCreated::class, OrderPaid::class, OrderCancelled::class]);

        $pending = $this->orderService->markPendingPayment(
            $this->orderService->createDraft(Client::factory()->create()),
        );

        Event::assertDispatchedTimes(OrderCreated::class, 1);

        $cancelled = $this->orderService->cancel($pending, 'Customer changed mind');

        Event::assertDispatchedTimes(OrderCancelled::class, 1);
        Event::assertDispatched(
            OrderCancelled::class,
            fn (OrderCancelled $event): bool => $event->order->is($cancelled),
        );
        Event::assertNotDispatched(OrderPaid::class);

        $this->orderService->cancel($cancelled->fresh() ?? $cancelled);
        Event::assertDispatchedTimes(OrderCancelled::class, 1);
    }

    public function test_create_from_checkout_dispatches_order_created_once(): void
    {
        Event::fake([OrderCreated::class, OrderPaid::class, OrderCancelled::class]);
        config(['corepanel.billing.tax_preview_rate' => 0]);

        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing()->create([
            'slug' => 'event-checkout',
        ]);

        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreate($client, null);
        $cartService->addItem($cart, CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => BillingCycle::Monthly->value,
        ]));

        $order = $this->orderService->createFromCheckout(
            $cart->fresh(['items.product', 'client']),
            CheckoutDraftData::fromArray([
                'contact_name' => 'Event User',
                'contact_email' => 'event@example.test',
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
            ]),
        );

        Event::assertDispatched(
            OrderCreated::class,
            fn (OrderCreated $event): bool => $event->order->is($order),
        );
        Event::assertDispatchedTimes(OrderCreated::class, 1);
        Event::assertNotDispatched(OrderPaid::class);
        Event::assertNotDispatched(OrderCancelled::class);
    }

    public function test_full_lifecycle_dispatches_created_then_paid(): void
    {
        Event::fake([OrderCreated::class, OrderPaid::class, OrderCancelled::class]);

        $paid = $this->orderService->markPaid(
            $this->orderService->markPendingPayment(
                $this->orderService->createDraft(Client::factory()->create()),
            ),
        );

        Event::assertDispatchedTimes(OrderCreated::class, 1);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
        Event::assertNotDispatched(OrderCancelled::class);
        Event::assertDispatched(
            OrderPaid::class,
            fn (OrderPaid $event): bool => $event->order->is($paid),
        );
    }

    public function test_create_from_admin_builds_draft_with_items_and_created_by(): void
    {
        config(['corepanel.billing.tax_preview_rate' => 0]);

        $creator = \App\Models\User::factory()->create();
        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing([BillingCycle::Monthly], '10.00', '2.00')->create();

        $order = $this->orderService->createFromAdmin(
            $client,
            $creator,
            CheckoutDraftData::fromArray([
                'contact_name' => 'Staff Order',
                'contact_email' => 'staff@example.test',
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
            ]),
            [
                CartItemData::fromArray([
                    'product_id' => $product->id,
                    'billing_cycle' => BillingCycle::Monthly->value,
                    'quantity' => 2,
                ]),
            ],
            notes: 'Admin note',
        );

        $this->assertSame(OrderStatus::Draft, $order->status);
        $this->assertSame(OrderSource::Admin, $order->source);
        $this->assertSame($creator->id, $order->created_by);
        $this->assertSame('Admin note', $order->notes);
        $this->assertSame(1, $order->items->count());
        $this->assertSame('10.00', $order->items->first()->unit_price);
        $this->assertSame('2.00', $order->items->first()->setup_fee);
        $this->assertSame('22.00', $order->items->first()->line_total);
        $this->assertSame('20.00', $order->subtotal_recurring);
        $this->assertSame('2.00', $order->subtotal_setup);
        $this->assertSame('22.00', $order->total_amount);
    }

    public function test_create_from_admin_submit_as_pending_dispatches_order_created(): void
    {
        Event::fake([OrderCreated::class, OrderPaid::class, OrderCancelled::class]);
        config(['corepanel.billing.tax_preview_rate' => 0]);

        $creator = \App\Models\User::factory()->create();
        $client = Client::factory()->create();
        $product = Product::factory()->published()->withPricing()->create();

        $order = $this->orderService->createFromAdmin(
            $client,
            $creator,
            CheckoutDraftData::fromArray([
                'contact_name' => 'Pending Admin',
                'contact_email' => 'pending@example.test',
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
            ]),
            [
                CartItemData::fromArray([
                    'product_id' => $product->id,
                    'billing_cycle' => BillingCycle::Monthly->value,
                ]),
            ],
            submitAsPending: true,
        );

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        Event::assertDispatchedTimes(OrderCreated::class, 1);
        Event::assertDispatched(
            OrderCreated::class,
            fn (OrderCreated $event): bool => $event->order->is($order),
        );
    }

    public function test_create_from_admin_rejects_empty_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->orderService->createFromAdmin(
            Client::factory()->create(),
            \App\Models\User::factory()->create(),
            CheckoutDraftData::fromArray([
                'contact_name' => 'Empty',
                'contact_email' => 'empty@example.test',
                'address' => '1 Street',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'FR',
                'payment_method' => 'manual_transfer',
            ]),
            [],
        );
    }
}
