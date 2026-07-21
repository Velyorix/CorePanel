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
use Core\Sync\DataTransferObjects\ServiceExternalState;
use Core\Sync\Enums\ServiceSyncDivergenceType;
use Core\Sync\Services\ServiceSyncComparisonService;
use Core\Sync\Services\ServiceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSyncComparisonTest extends TestCase
{
    use RefreshDatabase;

    private ServiceSyncComparisonService $comparison;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.services.sync.enabled' => true,
            'corepanel.services.sync.compare.status' => true,
            'corepanel.services.sync.compare.ip_address' => true,
            'corepanel.services.sync.compare.hostname' => true,
            'corepanel.services.sync.compare.external_id' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));

        $this->comparison = app(ServiceSyncComparisonService::class);
    }

    public function test_comparison_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceSyncComparisonService::class),
            app(ServiceSyncComparisonService::class),
        );
    }

    public function test_detects_no_divergence_when_remote_state_matches_local(): void
    {
        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-10',
            'hostname' => 'service-10.stub.local',
            'ip_address' => '10.255.0.11',
        ]);

        $response = ProvisioningResponse::success(
            externalId: 'stub-10',
            hostname: 'service-10.stub.local',
            ipAddress: '10.255.0.11',
            payload: ['remote_status' => 'active'],
        );

        $result = $this->comparison->compare($service, $response);

        $this->assertFalse($result->hasDivergences());
    }

    public function test_detects_status_mismatch(): void
    {
        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-11',
        ]);

        $response = ProvisioningResponse::success(
            externalId: 'stub-11',
            payload: ['remote_status' => 'suspended'],
        );

        $result = $this->comparison->compare($service, $response);

        $this->assertTrue($result->hasDivergences());
        $this->assertSame(ServiceSyncDivergenceType::StatusMismatch, $result->divergences[0]->type);
        $this->assertSame(ServiceStatus::Active->value, $result->divergences[0]->local);
        $this->assertSame(ServiceStatus::Suspended->value, $result->divergences[0]->remote);
    }

    public function test_detects_ip_change(): void
    {
        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-12',
            'ip_address' => '10.0.0.1',
        ]);

        $response = ProvisioningResponse::success(
            externalId: 'stub-12',
            ipAddress: '10.0.0.99',
            payload: ['remote_status' => 'active'],
        );

        $result = $this->comparison->compare($service, $response);

        $this->assertTrue($result->hasDivergences());
        $this->assertSame(ServiceSyncDivergenceType::IpChanged, $result->divergences[0]->type);
        $this->assertSame('10.0.0.1', $result->divergences[0]->local);
        $this->assertSame('10.0.0.99', $result->divergences[0]->remote);
    }

    public function test_detects_external_deletion_from_failed_not_found_response(): void
    {
        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-gone',
        ]);

        $response = ProvisioningResponse::failed(
            message: 'Remote resource not found.',
            payload: ['reason' => 'not_found'],
        );

        $result = $this->comparison->compare($service, $response);

        $this->assertTrue($result->hasDivergences());
        $this->assertSame(ServiceSyncDivergenceType::ExternalDeleted, $result->divergences[0]->type);
        $this->assertFalse($result->external->exists);
    }

    public function test_external_state_parser_maps_remote_status_aliases(): void
    {
        $state = ServiceExternalState::fromProvisioningResponse(
            ProvisioningResponse::success(payload: ['remote_status' => 'running']),
        );

        $this->assertTrue($state->exists);
        $this->assertSame(ServiceStatus::Active, $state->status);
    }

    public function test_poll_marks_diverged_services_without_changing_local_status(): void
    {
        app(ProviderRegistry::class)->registerServer(new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Diverging Stub';
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
                    ipAddress: '203.0.113.50',
                    payload: ['remote_status' => 'active'],
                );
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-diverged',
            'ip_address' => '10.0.0.5',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-diverged');

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(0, $result->polled);
        $this->assertSame(1, $result->diverged);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame('10.0.0.5', $service->fresh()->ip_address);

        $mapping = ProviderResourceMapping::query()->where('service_id', $service->id)->first();
        $this->assertFalse((bool) ($mapping?->metadata['in_sync'] ?? true));
        $this->assertSame('ip_changed', $mapping?->metadata['last_divergences'][0]['type'] ?? null);
    }

    public function test_poll_detects_external_deletion_as_divergence_not_failure(): void
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
            'external_id' => 'stub-missing',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-missing');

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(1, $result->diverged);
        $this->assertSame(0, $result->failed);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);

        $mapping = ProviderResourceMapping::query()->where('service_id', $service->id)->first();
        $this->assertSame('external_deleted', $mapping?->metadata['last_divergences'][0]['type'] ?? null);
    }
}
