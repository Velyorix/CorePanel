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
use Core\Sync\Jobs\ServiceSyncJob;
use Core\Sync\Services\ServiceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ServiceSyncJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.services.sync.enabled' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
    }

    public function test_sync_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceSyncService::class),
            app(ServiceSyncService::class),
        );
    }

    public function test_polls_syncable_services_from_external_providers(): void
    {
        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-101',
            'hostname' => 'syncable.example.test',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-101');

        Service::factory()->create([
            'module' => 'stub',
            'external_id' => null,
            'status' => ServiceStatus::Active,
        ]);

        Service::factory()->create([
            'module' => 'stub',
            'external_id' => 'stub-pending',
            'status' => ServiceStatus::Pending,
        ]);

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(1, $result->polled);
        $this->assertSame(0, $result->failed);
        $this->assertSame(0, $result->skipped);

        $mapping = ProviderResourceMapping::query()->where('service_id', $service->id)->first();
        $this->assertNotNull($mapping?->synced_at);
        $this->assertNotNull($mapping?->metadata['last_poll_at'] ?? null);
        $this->assertTrue((bool) ($mapping?->metadata['in_sync'] ?? false));
    }

    public function test_records_failed_poll_without_mutating_service_status(): void
    {
        app(ProviderRegistry::class)->registerServer(new class implements ServerProviderInterface
        {
            public function key(): string
            {
                return 'stub';
            }

            public function label(): string
            {
                return 'Failing Stub';
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
                return ProvisioningResponse::failed('Remote API unavailable.');
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-202',
        ]);

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(0, $result->polled);
        $this->assertSame(1, $result->failed);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
    }

    public function test_job_delegates_to_sync_service(): void
    {
        $service = \Mockery::mock(ServiceSyncService::class);
        $service->shouldReceive('pollAll')
            ->once()
            ->andReturn(new \Core\Sync\DataTransferObjects\ServiceSyncResult);

        app(ServiceSyncJob::class)->handle($service);
    }

    public function test_service_sync_job_is_scheduled_every_five_minutes_by_default(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(ServiceSyncJob::class, $output);
        $this->assertStringContainsString('*/5', $output);
    }

    public function test_polling_is_skipped_when_disabled_in_config(): void
    {
        config(['corepanel.services.sync.enabled' => false]);

        Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-disabled',
        ]);

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(0, $result->total());
    }
}
