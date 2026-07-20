<?php

namespace Tests\Unit\Provisioning;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Core\Providers\Services\ProviderRegistry;
use Core\Provisioning\Enums\ProviderResourceType;
use Core\Provisioning\Exceptions\ProviderResourceMappingException;
use Core\Provisioning\Models\ProviderResourceMapping;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Provisioning\Services\ProvisioningEngine;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderResourceMappingTest extends TestCase
{
    use RefreshDatabase;

    private ProviderResourceMappingService $mappings;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mappings = app(ProviderResourceMappingService::class);
        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
    }

    public function test_mapping_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ProviderResourceMappingService::class),
            app(ProviderResourceMappingService::class),
        );
    }

    public function test_upsert_creates_mapping_and_syncs_service_external_id(): void
    {
        $service = Service::factory()->create([
            'module' => 'pterodactyl',
            'external_id' => null,
        ]);

        $mapping = $this->mappings->upsert(
            service: $service,
            externalId: 'srv-100',
            metadata: ['node' => 'eu-1'],
        );

        $this->assertSame($service->id, $mapping->service_id);
        $this->assertSame('pterodactyl', $mapping->module);
        $this->assertSame('srv-100', $mapping->external_id);
        $this->assertSame(ProviderResourceType::Server, $mapping->resource_type);
        $this->assertSame(['node' => 'eu-1'], $mapping->metadata);
        $this->assertSame('srv-100', $service->fresh()->external_id);
    }

    public function test_find_by_external_id_resolves_service(): void
    {
        $service = Service::factory()->create(['module' => 'proxmox']);

        $this->mappings->upsert($service, 'vm-55');

        $found = $this->mappings->findServiceByExternalId('proxmox', 'vm-55');

        $this->assertNotNull($found);
        $this->assertTrue($found->is($service));
    }

    public function test_upsert_is_idempotent_for_same_service(): void
    {
        $service = Service::factory()->create(['module' => 'stub']);

        $first = $this->mappings->upsert($service, 'ext-1');
        $second = $this->mappings->upsert($service, 'ext-1', metadata: ['v' => 2]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProviderResourceMapping::query()->count());
        $this->assertSame(['v' => 2], $second->metadata);
    }

    public function test_upsert_rejects_external_id_owned_by_another_service(): void
    {
        $owner = Service::factory()->create(['module' => 'stub']);
        $other = Service::factory()->create(['module' => 'stub']);

        $this->mappings->upsert($owner, 'shared-id');

        $this->expectException(ProviderResourceMappingException::class);
        $this->expectExceptionMessage('already mapped to another service');

        $this->mappings->upsert($other, 'shared-id');
    }

    public function test_sync_from_provisioning_response_persists_mapping(): void
    {
        $service = Service::factory()->create(['module' => 'stub']);

        $mapping = $this->mappings->syncFromProvisioningResponse(
            $service,
            ProvisioningResponse::success(
                externalId: 'ext-9',
                hostname: 'host-9.example.test',
                ipAddress: '203.0.113.9',
                nodeId: 4,
            ),
        );

        $this->assertNotNull($mapping);
        $this->assertSame('ext-9', $mapping->external_id);
        $this->assertSame('host-9.example.test', $mapping->metadata['hostname']);
        $this->assertSame(4, $mapping->metadata['node_id']);
    }

    public function test_provisioning_success_writes_resource_mapping(): void
    {
        $this->registry->registerServer($this->makeProvider(
            'stub',
            ProvisioningResponse::success(
                externalId: 'mapped-1',
                hostname: 'mapped.example.test',
            ),
        ));

        $service = Service::factory()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Pending,
        ]);

        $response = app(ProvisioningEngine::class)->provision($service);

        $this->assertSame(ProviderOperationStatus::Success, $response->status);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertDatabaseHas('provider_resource_mappings', [
            'service_id' => $service->id,
            'module' => 'stub',
            'external_id' => 'mapped-1',
            'resource_type' => ProviderResourceType::Server->value,
        ]);
        $this->assertTrue($service->fresh()->providerResourceMappings()->exists());
    }

    public function test_provision_skips_provider_when_mapping_already_exists(): void
    {
        $calls = (object) ['count' => 0];
        $this->registry->registerServer($this->makeCountingProvider('stub', $calls));

        $service = Service::factory()->provisioning()->create([
            'module' => 'stub',
            'external_id' => null,
        ]);

        $this->mappings->upsert($service, 'already-mapped');

        $response = app(ProvisioningEngine::class)->provision($service->fresh() ?? $service);

        $this->assertSame(ProviderOperationStatus::Skipped, $response->status);
        $this->assertSame(0, $calls->count);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame('already-mapped', $service->fresh()->external_id);
    }

    public function test_forget_removes_mapping(): void
    {
        $service = Service::factory()->create(['module' => 'stub']);
        $this->mappings->upsert($service, 'to-remove');

        $this->assertTrue($this->mappings->forget($service));
        $this->assertFalse($this->mappings->hasMapping($service));
    }

    private function makeProvider(string $key, ProvisioningResponse $response): ServerProviderInterface
    {
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

    private function makeCountingProvider(string $key, object $counter): ServerProviderInterface
    {
        return new class($key, $counter) implements ServerProviderInterface
        {
            public function __construct(
                private readonly string $keyValue,
                private readonly object $counter,
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
                $this->counter->count++;

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
        };
    }
}
