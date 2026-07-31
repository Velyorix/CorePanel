<?php

namespace Tests\Feature\Provisioning;

use Core\Clients\Models\Client;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\OrderItem;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Jobs\ProvisionServiceJob;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Listeners\CreateServicesOnOrderPaid;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeServerProvider;
use Tests\TestCase;

class ProvisioningOnOrderPaidTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
    }

    public function test_order_paid_queues_provisioning_for_auto_provisionable_products(): void
    {
        Queue::fake();

        $this->registry->registerServer($this->makeProvider('pterodactyl'));

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('pterodactyl')
            ->withProvisioningRules(['auto_provision' => true])
            ->create();

        $order = app(OrderService::class)->markPendingPayment(
            app(OrderService::class)->createDraft(Client::factory()->create()),
        );

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '20.00',
            'setup_fee' => '0.00',
            'line_total' => '20.00',
        ]);

        $paid = app(OrderService::class)->markPaid($order);

        app(CreateServicesOnOrderPaid::class)->handle(new OrderPaid($paid));

        $service = Service::query()->firstOrFail();
        $this->assertSame(ServiceStatus::Pending, $service->status);

        Queue::assertPushed(ProvisionServiceJob::class, function (ProvisionServiceJob $job) use ($service): bool {
            return $job->serviceId === $service->id;
        });
    }

    public function test_order_paid_does_not_queue_when_auto_provision_disabled(): void
    {
        Queue::fake();

        $this->registry->registerServer($this->makeProvider('pterodactyl'));

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('pterodactyl')
            ->withProvisioningRules(['auto_provision' => false])
            ->create();

        $order = app(OrderService::class)->markPendingPayment(
            app(OrderService::class)->createDraft(Client::factory()->create()),
        );

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '20.00',
            'setup_fee' => '0.00',
            'line_total' => '20.00',
        ]);

        $paid = app(OrderService::class)->markPaid($order);
        app(CreateServicesOnOrderPaid::class)->handle(new OrderPaid($paid));

        Queue::assertNothingPushed();
        $this->assertSame(ServiceStatus::Pending, Service::query()->firstOrFail()->status);
    }

    public function test_sync_queue_provisions_service_after_order_paid(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'pterodactyl',
            ProvisioningResponse::success(
                externalId: 'srv-42',
                hostname: 'game-01.example.test',
                ipAddress: '198.51.100.10',
            ),
        ));

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('pterodactyl')
            ->withProvisioningRules(['auto_provision' => true])
            ->create();

        $order = app(OrderService::class)->markPendingPayment(
            app(OrderService::class)->createDraft(Client::factory()->create()),
        );

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '20.00',
            'setup_fee' => '0.00',
            'line_total' => '20.00',
        ]);

        $paid = app(OrderService::class)->markPaid($order);
        app(CreateServicesOnOrderPaid::class)->handle(new OrderPaid($paid));

        $service = Service::query()->firstOrFail();
        $this->assertSame(ServiceStatus::Active, $service->status);
        $this->assertSame('srv-42', $service->external_id);
        $this->assertSame('game-01.example.test', $service->hostname);
        $this->assertSame('198.51.100.10', $service->ip_address);
    }

    public function test_job_handle_delegates_to_engine(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::success(externalId: 'from-job'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        (new ProvisionServiceJob($service->id))->handle(app(ProvisioningEngine::class));

        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame('from-job', $service->fresh()->external_id);
    }

    private function makeProvider(
        string $key,
        ?ProvisioningResponse $response = null,
    ): ServerProviderInterface {
        return new FakeServerProvider(
            $key,
            $response ?? ProvisioningResponse::success(externalId: 'ext-default'),
        );
    }
}
