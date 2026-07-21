<?php

namespace Tests\Feature\Nodes;

use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Services\NodeAllocationAlgorithm;
use Core\Nodes\Services\NodeOverloadService;
use Core\Products\Models\Product;
use Core\Provisioning\Exceptions\NodeProvisioningDeferredException;
use Core\Provisioning\Jobs\ProvisionServiceJob;
use Core\Provisioning\Services\NodeSelectionService;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NodeOverloadProtectionTest extends TestCase
{
    use RefreshDatabase;

    private NodeAllocationAlgorithm $algorithm;

    private NodeSelectionService $selection;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.nodes.allocation.require_credentials' => false,
            'corepanel.nodes.allocation.load_balancing.enabled' => false,
            'corepanel.nodes.overload.enabled' => true,
            'corepanel.nodes.overload.defer_on_overload' => true,
            'corepanel.nodes.overload.queue_delay_seconds' => 45,
            'corepanel.nodes.overload.service_fill_threshold' => 0.95,
            'corepanel.provisioning.require_node_for_assigned_group' => true,
        ]);

        Cache::flush();

        $this->algorithm = app(NodeAllocationAlgorithm::class);
        $this->selection = app(NodeSelectionService::class);
    }

    public function test_overload_service_detects_service_capacity_saturation(): void
    {
        $node = Node::factory()->forModule('stub')->withCapacity(1)->create([
            'hostname' => 'full.example.test',
        ]);

        Service::factory()->create([
            'module' => 'stub',
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $node->loadCount('services');

        $this->assertTrue(app(NodeOverloadService::class)->isOverloaded($node));
    }

    public function test_primary_selection_skips_overloaded_node(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'overload-group']);

        $overloaded = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'overloaded.example.test',
        ]);
        Service::factory()->count(9)->create([
            'module' => 'stub',
            'node_id' => $overloaded->id,
            'status' => ServiceStatus::Active,
        ]);

        $available = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'available.example.test',
        ]);
        Service::factory()->create([
            'module' => 'stub',
            'node_id' => $available->id,
            'status' => ServiceStatus::Active,
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($available->id, $selected->id);
    }

    public function test_fallback_node_receives_assignment_when_primary_pool_is_overloaded(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'fallback-group']);

        $overloaded = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(1)->create([
            'hostname' => 'primary-full.example.test',
        ]);
        Service::factory()->create([
            'module' => 'stub',
            'node_id' => $overloaded->id,
            'status' => ServiceStatus::Active,
        ]);

        $fallback = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'fallback.example.test',
            'config' => [
                'allocation' => ['is_fallback' => true],
            ],
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($fallback->id, $selected->id);
    }

    public function test_select_for_service_defers_when_entire_pool_is_overloaded(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'saturated-group']);

        $node = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(1)->create([
            'hostname' => 'only-node.example.test',
        ]);
        Service::factory()->create([
            'module' => 'stub',
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $product = Product::factory()
            ->withModule('stub')
            ->withProvisioningRules([
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $this->expectException(NodeProvisioningDeferredException::class);
        $this->expectExceptionMessage('All eligible nodes are overloaded');

        try {
            $this->selection->selectForService($service);
        } catch (NodeProvisioningDeferredException $exception) {
            $this->assertSame(45, $exception->retryAfterSeconds);

            throw $exception;
        }
    }

    public function test_provision_job_releases_with_delay_on_overload(): void
    {
        $group = NodeGroup::factory()->create(['key' => 'job-defer-group']);

        $node = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(1)->create([
            'hostname' => 'job-full.example.test',
        ]);
        Service::factory()->create([
            'module' => 'stub',
            'node_id' => $node->id,
            'status' => ServiceStatus::Active,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Stub';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(externalId: 'should-not-run');
            }

            public function suspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function unsuspend(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function terminate(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }

            public function reinstall(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success();
            }
        });

        $product = Product::factory()
            ->withModule('stub')
            ->withProvisioningRules([
                'auto_provision' => true,
                'node_group_id' => $group->id,
                'node_group_key' => $group->key,
            ])
            ->create();

        $service = Service::factory()->forProduct($product)->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $job = new class($service->id) extends ProvisionServiceJob
        {
            public ?int $releasedAfter = null;

            public function release($delay = 0): void
            {
                $this->releasedAfter = (int) $delay;
            }
        };

        $job->handle(app(ProvisioningEngine::class));

        $this->assertSame(45, $job->releasedAfter);
        $this->assertSame(ServiceStatus::Provisioning, $service->fresh()->status);
    }

    public function test_overload_protection_can_be_disabled(): void
    {
        config(['corepanel.nodes.overload.enabled' => false]);

        $group = NodeGroup::factory()->create(['key' => 'disabled-overload']);

        $busy = Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'busy.example.test',
        ]);
        Service::factory()->count(9)->create([
            'module' => 'stub',
            'node_id' => $busy->id,
            'status' => ServiceStatus::Active,
        ]);

        Node::factory()->forGroup($group)->forModule('stub')->withCapacity(10)->create([
            'hostname' => 'idle.example.test',
        ]);

        $selected = $this->algorithm->selectBest($group, 'stub');

        $this->assertNotNull($selected);
        $this->assertSame($busy->id, $selected->id);
    }
}
