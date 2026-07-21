<?php

namespace Tests\Unit\Provisioning;

use Core\Nodes\Models\Node;
use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Provisioning\Services\ProvisioningDeadLetterService;
use Core\Provisioning\Services\ProvisioningRollbackService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProvisioningRollbackServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_clears_mapping_and_external_id_but_keeps_node_by_default(): void
    {
        $node = Node::factory()->create();
        $service = Service::factory()->failed()->create([
            'module' => 'stub',
            'external_id' => 'orphan-ext',
            'node_id' => $node->id,
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'orphan-ext');

        $result = app(ProvisioningRollbackService::class)->rollbackLocalState($service);

        $service->refresh();
        $this->assertNull($service->external_id);
        $this->assertSame($node->id, $service->node_id);
        $this->assertFalse(app(ProviderResourceMappingService::class)->hasMapping($service));
        $this->assertContains('mapping', $result['cleared']);
        $this->assertContains('external_id', $result['cleared']);
        $this->assertNotContains('node_id', $result['cleared']);
    }

    public function test_rollback_can_release_node(): void
    {
        $node = Node::factory()->create();
        $service = Service::factory()->failed()->create([
            'module' => 'stub',
            'node_id' => $node->id,
        ]);

        app(ProvisioningRollbackService::class)->rollbackLocalState($service, [
            'clear_node' => true,
        ]);

        $this->assertNull($service->fresh()->node_id);
    }

    public function test_requeue_rolls_back_local_identity_then_dispatches_job(): void
    {
        Queue::fake();

        $service = Service::factory()->failed()->create([
            'module' => 'stub',
            'external_id' => 'stale',
        ]);
        app(ProviderResourceMappingService::class)->upsert($service, 'stale');

        $letter = app(ProvisioningDeadLetterService::class)->record($service->id, attempts: 3);
        $updated = app(ProvisioningDeadLetterService::class)->requeue($letter, notes: 'retry');

        $this->assertSame(ProvisioningDeadLetterStatus::Requeued, $updated->status);
        $this->assertNull($service->fresh()->external_id);
        $this->assertFalse(app(ProviderResourceMappingService::class)->hasMapping($service));
        Queue::assertPushed(\Core\Provisioning\Jobs\ProvisionServiceJob::class);
    }
}
