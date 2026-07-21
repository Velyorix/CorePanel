<?php

namespace Tests\Feature\Sync;

use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Models\ProviderResourceMapping;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Sync\Enums\ServiceSyncDivergenceType;
use Core\Sync\Enums\ServiceSyncResolutionAction;
use Core\Sync\Services\ServiceSyncComparisonService;
use Core\Sync\Services\ServiceSyncResolutionService;
use Core\Sync\Services\ServiceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSyncResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.services.sync.enabled' => true,
            'corepanel.services.sync.resolve.enabled' => true,
            'corepanel.services.sync.resolve.external_deleted' => true,
            'corepanel.services.sync.resolve.status_mismatch' => true,
            'corepanel.services.sync.resolve.ip_address' => true,
            'corepanel.services.sync.resolve.hostname' => true,
            'corepanel.services.sync.resolve.external_id' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
    }

    public function test_resolution_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceSyncResolutionService::class),
            app(ServiceSyncResolutionService::class),
        );
    }

    public function test_terminates_local_service_when_external_resource_is_deleted(): void
    {
        app(ProviderRegistry::class)->registerServer(new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Missing Stub';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(externalId: 'unused');
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

            public function getStatus(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::failed(
                    message: 'Resource does not exist.',
                    payload: ['reason' => 'not_found'],
                );
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-gone',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-gone');

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(1, $result->resolved);
        $this->assertSame(0, $result->diverged);
        $this->assertSame(ServiceStatus::Terminated, $service->fresh()->status);
        $this->assertNotNull($service->fresh()->terminated_at);

        $mapping = ProviderResourceMapping::query()->where('service_id', $service->id)->first();
        $this->assertTrue((bool) ($mapping?->metadata['in_sync'] ?? false));
        $this->assertSame(
            ServiceSyncResolutionAction::TerminatedLocally->value,
            $mapping?->metadata['last_resolutions'][0]['action'] ?? null,
        );
    }

    public function test_syncs_remote_ip_to_local_service(): void
    {
        app(ProviderRegistry::class)->registerServer(new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Ip Stub';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(externalId: 'unused');
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

            public function getStatus(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(
                    externalId: (string) $request->externalId,
                    ipAddress: '203.0.113.77',
                    payload: ['remote_status' => 'active'],
                );
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-ip',
            'ip_address' => '10.0.0.5',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-ip');

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(1, $result->resolved);
        $this->assertSame('203.0.113.77', $service->fresh()->ip_address);
    }

    public function test_syncs_remote_status_to_local_service(): void
    {
        app(ProviderRegistry::class)->registerServer(new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Status Stub';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(externalId: 'unused');
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

            public function getStatus(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(
                    externalId: (string) $request->externalId,
                    payload: ['remote_status' => 'suspended'],
                );
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-status',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-status');

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(1, $result->resolved);
        $this->assertSame(ServiceStatus::Suspended, $service->fresh()->status);
        $this->assertNotNull($service->fresh()->suspended_at);
    }

    public function test_resolution_can_be_disabled_to_preserve_divergence_only_behavior(): void
    {
        config(['corepanel.services.sync.resolve.enabled' => false]);

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-no-resolve',
            'ip_address' => '10.0.0.5',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-no-resolve');

        app(ProviderRegistry::class)->registerServer(new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'No Resolve Stub';
            }

            public function create(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::success(externalId: 'unused');
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

            public function getStatus(ProvisioningRequest $request): ProvisioningResponse
            {
                return ProvisioningResponse::failed(
                    message: 'Resource does not exist.',
                    payload: ['reason' => 'not_found'],
                );
            }
        });

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(1, $result->diverged);
        $this->assertSame(0, $result->resolved);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
    }

    public function test_resolution_service_terminates_from_comparison_result(): void
    {
        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-direct',
        ]);

        $comparison = app(ServiceSyncComparisonService::class)->compare(
            $service,
            ProvisioningResponse::failed(
                message: 'Remote resource not found.',
                payload: ['reason' => 'not_found'],
            ),
        );

        $this->assertSame(ServiceSyncDivergenceType::ExternalDeleted, $comparison->divergences[0]->type);

        $resolution = app(ServiceSyncResolutionService::class)->resolve($service, $comparison);

        $this->assertTrue($resolution->isFullyResolved());
        $this->assertSame(ServiceStatus::Terminated, $service->fresh()->status);
    }
}
