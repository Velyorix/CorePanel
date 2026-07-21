<?php

namespace Tests\Feature\Provisioning;

use Core\Clients\Models\Client;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\OrderItem;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Provisioning\Events\ServiceProvisioned;
use Core\Provisioning\Events\ServiceProvisioningFailed;
use Core\Provisioning\Exceptions\ProvisioningAttemptFailedException;
use Core\Provisioning\Jobs\ProvisionServiceJob;
use Core\Provisioning\Models\ProviderResourceMapping;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Listeners\CreateServicesOnOrderPaid;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * End-to-end provisioning coverage: stub provider, mapping, node assignment,
 * domain events, retry exhaustion, and dead-letter review state.
 */
class ProvisioningEngineFeatureTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRegistry $registry;

    private ProvisioningEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.provisioning.stub.enabled' => true,
            'corepanel.provisioning.stub.key' => 'stub',
            'corepanel.provisioning.stub.fail_operations' => [],
            'corepanel.provisioning.rollback_on_failure' => true,
            'corepanel.provisioning.require_node_for_assigned_group' => true,
            'corepanel.provisioning.tries' => 3,
            'corepanel.provisioning.backoff_seconds' => [1, 2, 3],
        ]);

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
        $this->registry->registerServer(app(StubServerProvider::class));

        $this->engine = app(ProvisioningEngine::class);
    }

    public function test_stub_provider_provisions_service_with_mapping_node_and_event(): void
    {
        Event::fake([ServiceProvisioned::class, ServiceProvisioningFailed::class]);

        $group = NodeGroup::factory()->create(['key' => 'stub-eu']);
        $node = Node::factory()->forGroup($group)->forModule('stub')->create([
            'name' => 'stub-node-1',
        ]);

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('stub')
            ->withProvisioningRules([
                'auto_provision' => true,
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->create([
            'product_id' => $product->id,
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'hostname' => null,
            'external_id' => null,
            'node_id' => null,
        ]);

        $response = $this->engine->provision($service);

        $this->assertSame(ProviderOperationStatus::Success, $response->status);

        $service->refresh();
        $this->assertSame(ServiceStatus::Active, $service->status);
        $this->assertSame('stub-'.$service->id, $service->external_id);
        $this->assertSame('service-'.$service->id.'.stub.local', $service->hostname);
        $this->assertSame($node->id, $service->node_id);
        $this->assertNotNull($service->provisioned_at);
        $this->assertNotNull($service->ip_address);

        $this->assertDatabaseHas('provider_resource_mappings', [
            'service_id' => $service->id,
            'module' => 'stub',
            'external_id' => 'stub-'.$service->id,
        ]);

        Event::assertDispatched(ServiceProvisioned::class, function (ServiceProvisioned $event) use ($service): bool {
            return $event->service->is($service)
                && $event->externalId === 'stub-'.$service->id;
        });
        Event::assertNotDispatched(ServiceProvisioningFailed::class);
    }

    public function test_external_id_mapping_survives_idempotent_reprovision(): void
    {
        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'external_id' => null,
        ]);

        $this->engine->provision($service);
        $service->refresh();

        $externalId = $service->external_id;
        $mappingId = ProviderResourceMapping::query()
            ->where('service_id', $service->id)
            ->value('id');

        $this->assertNotNull($externalId);
        $this->assertNotNull($mappingId);

        $second = $this->engine->provision($service->fresh() ?? $service);

        $this->assertSame(ProviderOperationStatus::Skipped, $second->status);
        $this->assertSame($externalId, $service->fresh()->external_id);
        $this->assertSame(1, ProviderResourceMapping::query()->where('service_id', $service->id)->count());
        $this->assertSame($mappingId, ProviderResourceMapping::query()->where('service_id', $service->id)->value('id'));
    }

    public function test_order_paid_queues_job_and_job_activates_via_stub(): void
    {
        Event::fake([ServiceProvisioned::class]);
        Queue::fake();

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('stub')
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
            'unit_price' => '15.00',
            'setup_fee' => '0.00',
            'line_total' => '15.00',
        ]);

        $paid = app(OrderService::class)->markPaid($order);
        app(CreateServicesOnOrderPaid::class)->handle(new OrderPaid($paid));

        $service = Service::query()->firstOrFail();
        $this->assertSame(ServiceStatus::Pending, $service->status);

        Queue::assertPushed(ProvisionServiceJob::class, function (ProvisionServiceJob $job) use ($service): bool {
            return $job->serviceId === $service->id;
        });

        (new ProvisionServiceJob($service->id))->handle($this->engine);

        $service->refresh();
        $this->assertSame(ServiceStatus::Active, $service->status);
        $this->assertSame('stub-'.$service->id, $service->external_id);

        Event::assertDispatched(ServiceProvisioned::class);
    }

    public function test_sync_stub_failure_marks_failed_rolls_back_mapping_and_dispatches_event(): void
    {
        Event::fake([ServiceProvisioned::class, ServiceProvisioningFailed::class]);

        config(['corepanel.provisioning.stub.fail_operations' => ['create']]);

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'external_id' => 'stale-ext',
            'hostname' => 'stale.example.test',
        ]);

        $response = $this->engine->provision($service);

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);

        $service->refresh();
        $this->assertSame(ServiceStatus::Failed, $service->status);
        $this->assertNull($service->external_id);
        $this->assertSame(0, ProviderResourceMapping::query()->where('service_id', $service->id)->count());

        Event::assertDispatched(ServiceProvisioningFailed::class, function (ServiceProvisioningFailed $event) use ($service): bool {
            return $event->service->is($service)
                && str_contains((string) $event->reason, 'forced create failure');
        });
        Event::assertNotDispatched(ServiceProvisioned::class);
    }

    public function test_job_retry_exhaustion_writes_dead_letter_and_failure_event(): void
    {
        Event::fake([ServiceProvisioned::class, ServiceProvisioningFailed::class]);

        config(['corepanel.provisioning.stub.fail_operations' => ['create']]);

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $job = new ProvisionServiceJob($service->id);

        try {
            $job->handle($this->engine);
            $this->fail('Expected ProvisioningAttemptFailedException');
        } catch (ProvisioningAttemptFailedException) {
            // First attempt stays retryable — service still provisioning.
        }

        $this->assertSame(ServiceStatus::Provisioning, $service->fresh()->status);
        $this->assertSame(0, ProvisioningDeadLetter::query()->count());
        Event::assertNotDispatched(ServiceProvisioningFailed::class);

        $job->failed(ProvisioningAttemptFailedException::fromMessage('Stub provider forced create failure.'));

        $service->refresh();
        $this->assertSame(ServiceStatus::Failed, $service->status);

        $letter = ProvisioningDeadLetter::query()->where('service_id', $service->id)->firstOrFail();
        $this->assertSame(ProvisioningDeadLetterStatus::PendingReview, $letter->status);
        $this->assertSame('stub', $letter->module);

        Event::assertDispatchedTimes(ServiceProvisioningFailed::class, 1);
        Event::assertNotDispatched(ServiceProvisioned::class);
    }

    public function test_engine_skips_queue_when_auto_provision_disabled(): void
    {
        Queue::fake();

        $product = Product::factory()
            ->ofType(ProductType::Vps)
            ->withModule('stub')
            ->withProvisioningRules(['auto_provision' => false])
            ->create();

        $service = Service::factory()->create([
            'product_id' => $product->id,
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $this->assertFalse($this->engine->shouldAutoQueue($service));
        $this->assertFalse($this->engine->queueIfEligible($service));

        Queue::assertNothingPushed();
    }
}
