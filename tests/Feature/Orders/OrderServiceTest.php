<?php

namespace Tests\Feature\Orders;

use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
