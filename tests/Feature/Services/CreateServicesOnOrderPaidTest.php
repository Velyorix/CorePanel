<?php

namespace Tests\Feature\Services;

use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Listeners\CreateServicesOnOrderPaid;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class CreateServicesOnOrderPaidTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;

    private ServiceCreationService $creation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = app(OrderService::class);
        $this->creation = app(ServiceCreationService::class);
    }

    public function test_service_creation_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceCreationService::class),
            app(ServiceCreationService::class),
        );
    }

    public function test_listener_handle_delegates_to_creation_service(): void
    {
        Event::fake([OrderPaid::class]);

        $order = $this->orders->markPendingPayment(
            $this->orders->createDraft(Client::factory()->create()),
        );

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '10.00',
            'setup_fee' => '0.00',
            'line_total' => '10.00',
        ]);

        $paid = $this->orders->markPaid($order);
        $this->assertSame(0, Service::query()->count());

        app(CreateServicesOnOrderPaid::class)->handle(new OrderPaid($paid));

        $this->assertSame(1, Service::query()->count());
        $this->assertSame(ServiceStatus::Pending, Service::query()->firstOrFail()->status);
    }

    public function test_mark_paid_creates_one_pending_service_per_order_item(): void
    {
        $client = Client::factory()->create();
        $vps = Product::factory()->ofType(ProductType::Vps)->withModule('pterodactyl')->create();
        $addon = Product::factory()->ofType(ProductType::Addon)->create(['module' => null]);

        $order = $this->orders->markPendingPayment(
            $this->orders->createDraft($client),
        );

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $vps->id,
            'product_name' => $vps->name,
            'product_slug' => $vps->slug,
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'options' => ['hostname' => 'node-01.example.test', 'ram' => '8'],
            'addons' => ['backup'],
            'config_data' => ['panel' => 'game'],
            'unit_price' => '20.00',
            'setup_fee' => '0.00',
            'line_total' => '20.00',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $addon->id,
            'product_name' => $addon->name,
            'product_slug' => $addon->slug,
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '5.00',
            'setup_fee' => '0.00',
            'line_total' => '5.00',
        ]);

        $paid = $this->orders->markPaid($order);

        $this->assertSame(OrderStatus::Paid, $paid->status);
        $this->assertSame(2, Service::query()->count());

        $vpsService = Service::query()->where('product_id', $vps->id)->firstOrFail();
        $this->assertSame(ServiceStatus::Pending, $vpsService->status);
        $this->assertSame($client->id, $vpsService->client_id);
        $this->assertSame($paid->id, $vpsService->order_id);
        $this->assertSame('pterodactyl', $vpsService->module);
        $this->assertSame(BillingCycle::Monthly, $vpsService->billing_cycle);
        $this->assertSame('node-01.example.test', $vpsService->hostname);
        $this->assertSame([
            'options' => ['hostname' => 'node-01.example.test', 'ram' => '8'],
            'addons' => ['backup'],
            'config' => ['panel' => 'game'],
        ], $vpsService->config_data);

        $addonService = Service::query()->where('product_id', $addon->id)->firstOrFail();
        $this->assertSame(ServiceStatus::Pending, $addonService->status);
        $this->assertNull($addonService->module);
        $this->assertNull($addonService->hostname);
    }

    public function test_creation_is_idempotent_for_same_order_items(): void
    {
        $order = $this->makePaidOrderWithItem();

        $this->assertSame(1, Service::query()->count());

        $again = $this->creation->createFromPaidOrder($order->fresh() ?? $order);
        $this->assertCount(0, $again);
        $this->assertSame(1, Service::query()->count());

        $this->orders->markPaid($order->fresh() ?? $order);
        $this->assertSame(1, Service::query()->count());
    }

    public function test_create_from_non_paid_order_throws(): void
    {
        $order = $this->orders->createDraft(Client::factory()->create());
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Services can only be created from paid orders.');

        $this->creation->createFromPaidOrder($order);
    }

    public function test_pending_payment_order_does_not_create_services(): void
    {
        $order = $this->orders->markPendingPayment(
            $this->orders->createDraft(Client::factory()->create()),
        );
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->assertSame(0, Service::query()->count());
    }

    private function makePaidOrderWithItem(): Order
    {
        $order = $this->orders->markPendingPayment(
            $this->orders->createDraft(Client::factory()->create()),
        );

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '10.00',
            'setup_fee' => '0.00',
            'line_total' => '10.00',
        ]);

        return $this->orders->markPaid($order);
    }
}
