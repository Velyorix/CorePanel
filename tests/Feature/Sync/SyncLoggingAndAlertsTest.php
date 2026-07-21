<?php

namespace Tests\Feature\Sync;

use Core\Admin\Models\AdminNotification;
use Core\Admin\Services\AdminNotificationService;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Models\Service;
use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Core\Sync\Models\SyncLog;
use Core\Sync\Services\ServiceSyncService;
use Core\Sync\Services\SyncAlertService;
use Core\Sync\Services\SyncLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncLoggingAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.services.sync.enabled' => true,
            'corepanel.services.sync.resolve.enabled' => false,
            'corepanel.sync.logs.service.enabled' => true,
            'corepanel.sync.logs.node.enabled' => true,
            'corepanel.sync.alerts.enabled' => true,
            'corepanel.provisioning.stub.enabled' => true,
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
    }

    public function test_sync_log_and_alert_services_are_registered_as_singletons(): void
    {
        $this->assertSame(app(SyncLogService::class), app(SyncLogService::class));
        $this->assertSame(app(SyncAlertService::class), app(SyncAlertService::class));
        $this->assertSame(app(AdminNotificationService::class), app(AdminNotificationService::class));
    }

    public function test_service_divergence_creates_sync_log_and_admin_notification(): void
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
                    ipAddress: '203.0.113.88',
                    payload: ['remote_status' => 'active'],
                );
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-alert',
            'ip_address' => '10.0.0.2',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-alert');

        app(ServiceSyncService::class)->pollAll();

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Service->value,
            'subject_id' => $service->id,
            'outcome' => SyncLogOutcome::Diverged->value,
        ]);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => SyncAlertService::TYPE_SERVICE_DIVERGED,
            'dedupe_key' => 'sync.service.'.$service->id,
            'variant' => 'warning',
        ]);

        $notification = AdminNotification::query()
            ->where('dedupe_key', 'sync.service.'.$service->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);
        $this->assertStringContainsString('IP change', $notification->message);
        $this->assertSame('ip_changed', $notification->metadata['divergences'][0]['type'] ?? null);
    }

    public function test_successful_service_poll_clears_existing_divergence_alert(): void
    {
        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-clear',
            'hostname' => 'clear.example.test',
            'ip_address' => '10.255.0.11',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-clear');

        app(AdminNotificationService::class)->upsert(
            type: SyncAlertService::TYPE_SERVICE_DIVERGED,
            dedupeKey: 'sync.service.'.$service->id,
            title: __('Service sync divergence'),
            message: __('Stale alert'),
            variant: 'warning',
        );

        app(ServiceSyncService::class)->pollAll();

        $notification = AdminNotification::query()
            ->where('dedupe_key', 'sync.service.'.$service->id)
            ->first();

        $this->assertNotNull($notification?->read_at);

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Service->value,
            'subject_id' => $service->id,
            'outcome' => SyncLogOutcome::Polled->value,
        ]);
    }

    public function test_failed_service_poll_creates_danger_notification(): void
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
                return ProvisioningResponse::failed(message: 'Remote API unavailable.');
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-fail-alert',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-fail-alert');

        app(ServiceSyncService::class)->pollAll();

        $this->assertDatabaseHas('sync_logs', [
            'subject_id' => $service->id,
            'outcome' => SyncLogOutcome::Failed->value,
        ]);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => SyncAlertService::TYPE_SERVICE_FAILED,
            'dedupe_key' => 'sync.service.'.$service->id,
            'variant' => 'danger',
        ]);
    }

    public function test_logging_and_alerts_can_be_disabled_via_config(): void
    {
        config([
            'corepanel.sync.logs.service.enabled' => false,
            'corepanel.sync.alerts.enabled' => false,
        ]);

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
                    ipAddress: '203.0.113.90',
                    payload: ['remote_status' => 'active'],
                );
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-disabled-log',
            'ip_address' => '10.0.0.3',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-disabled-log');

        app(ServiceSyncService::class)->pollAll();

        $this->assertSame(0, SyncLog::query()->count());
        $this->assertSame(0, AdminNotification::query()->count());
    }
}
