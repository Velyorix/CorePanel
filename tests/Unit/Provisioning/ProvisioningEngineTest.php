<?php

namespace Tests\Unit\Provisioning;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Exceptions\UnknownProviderException;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Exceptions\ProvisioningException;
use Core\Provisioning\Jobs\ProvisionServiceJob;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProvisioningEngineTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRegistry $registry;

    private ProvisioningEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
        $this->engine = app(ProvisioningEngine::class);
    }

    public function test_engine_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ProvisioningEngine::class),
            app(ProvisioningEngine::class),
        );
    }

    public function test_should_auto_queue_requires_auto_provision_and_registered_provider(): void
    {
        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $this->assertFalse($this->engine->shouldAutoQueue($service));

        $service->product->provisioningRules()->create([
            'auto_provision' => true,
        ]);
        $service->unsetRelation('product');

        $this->assertFalse($this->engine->shouldAutoQueue($service->fresh(['product.provisioningRules']) ?? $service));

        $this->registry->registerServer($this->makeProvider('stub'));

        $this->assertTrue($this->engine->shouldAutoQueue($service->fresh(['product.provisioningRules']) ?? $service));
    }

    public function test_queue_dispatches_provision_service_job(): void
    {
        Queue::fake();

        $service = Service::factory()->create(['module' => 'stub']);

        $this->engine->queue($service);

        Queue::assertPushed(ProvisionServiceJob::class, function (ProvisionServiceJob $job) use ($service): bool {
            return $job->serviceId === $service->id;
        });
    }

    public function test_provision_activates_service_on_provider_success(): void
    {
        $this->registry->registerServer($this->makeProvider('stub', ProvisioningResponse::success(
            externalId: 'ext-100',
            hostname: 'srv-100.example.test',
            ipAddress: '203.0.113.50',
            nodeId: 7,
        )));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
            'hostname' => null,
        ]);

        $response = $this->engine->provision($service);

        $this->assertSame(ProviderOperationStatus::Success, $response->status);

        $service->refresh();
        $this->assertSame(ServiceStatus::Active, $service->status);
        $this->assertSame('ext-100', $service->external_id);
        $this->assertSame('srv-100.example.test', $service->hostname);
        $this->assertSame('203.0.113.50', $service->ip_address);
        $this->assertSame(7, $service->node_id);
        $this->assertNotNull($service->provisioned_at);
    }

    public function test_provision_marks_failed_on_provider_failure(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::failed('Upstream unavailable'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $response = $this->engine->provision($service);

        $this->assertSame(ProviderOperationStatus::Failed, $response->status);
        $this->assertSame(ServiceStatus::Failed, $service->fresh()->status);
    }

    public function test_provision_leaves_provisioning_on_pending_response(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::pending('Awaiting remote job'),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $response = $this->engine->provision($service);

        $this->assertSame(ProviderOperationStatus::Pending, $response->status);
        $this->assertSame(ServiceStatus::Provisioning, $service->fresh()->status);
    }

    public function test_provision_is_idempotent_when_already_active_with_external_id(): void
    {
        $this->registry->registerServer($this->makeProvider('stub'));

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'already-there',
        ]);

        $response = $this->engine->provision($service);

        $this->assertSame(ProviderOperationStatus::Skipped, $response->status);
        $this->assertSame('already-there', $service->fresh()->external_id);
    }

    public function test_provision_throws_when_module_missing(): void
    {
        $service = Service::factory()->create([
            'module' => null,
            'status' => ServiceStatus::Pending,
        ]);

        $this->expectException(ProvisioningException::class);
        $this->expectExceptionMessage('has no module key');

        $this->engine->provision($service);
    }

    public function test_provision_throws_when_provider_unknown(): void
    {
        $service = Service::factory()->create([
            'module' => 'missing',
            'status' => ServiceStatus::Pending,
        ]);

        $this->expectException(UnknownProviderException::class);

        $this->engine->provision($service);
    }

    public function test_provision_throws_for_invalid_status(): void
    {
        $this->registry->registerServer($this->makeProvider('stub'));

        $service = Service::factory()->terminated()->create([
            'module' => 'stub',
        ]);

        $this->expectException(ProvisioningException::class);
        $this->expectExceptionMessage('cannot be provisioned');

        $this->engine->provision($service);
    }

    public function test_provision_can_retry_from_failed_status(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::success(externalId: 'retry-1'),
        ));

        $service = Service::factory()->failed()->create([
            'module' => 'stub',
        ]);

        $response = $this->engine->provision($service);

        $this->assertSame(ProviderOperationStatus::Success, $response->status);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame('retry-1', $service->fresh()->external_id);
    }

    private function makeProvider(
        string $key,
        ?ProvisioningResponse $response = null,
    ): ServerProviderInterface {
        $response ??= ProvisioningResponse::success(externalId: 'ext-default');

        return new class($key, $response) implements ServerProviderInterface
        {
            public function __construct(
                private readonly string $keyValue,
                private readonly ProvisioningResponse $response,
            ) {
            }

            public function key(): string
            {
                return $this->keyValue;
            }

            public function label(): string
            {
                return strtoupper($this->keyValue);
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return $this->response;
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
        };
    }
}
