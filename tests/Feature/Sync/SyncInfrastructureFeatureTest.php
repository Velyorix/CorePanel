<?php

namespace Tests\Feature\Sync;

use App\Models\User;
use Core\Admin\Models\AdminNotification;
use Core\Admin\Notifications\AdminNotificationFeed;
use Core\Admin\Services\AdminNotificationService;
use Core\Nodes\Models\Node;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Core\Sync\Jobs\NodeSyncJob;
use Core\Sync\Jobs\ServiceSyncJob;
use Core\Sync\Models\SyncLog;
use Core\Sync\Services\NodeSyncService;
use Core\Sync\Services\ServiceSyncComparisonService;
use Core\Sync\Services\ServiceSyncResolutionService;
use Core\Sync\Services\ServiceSyncService;
use Core\Sync\Services\SyncAlertService;
use Core\Sync\Services\SyncLogQueryService;
use Core\Sync\Services\SyncLogService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end sync coverage across service polling, divergence detection,
 * conflict resolution, logging, admin alerts, and manual admin actions.
 */
class SyncInfrastructureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.sync-infrastructure',
            'corepanel.provisioning.stub.enabled' => true,
            'corepanel.services.sync.enabled' => true,
            'corepanel.services.sync.resolve.enabled' => false,
            'corepanel.services.sync.compare.status' => true,
            'corepanel.services.sync.compare.ip_address' => true,
            'corepanel.nodes.sync.enabled' => true,
            'corepanel.sync.logs.service.enabled' => true,
            'corepanel.sync.logs.node.enabled' => true,
            'corepanel.sync.alerts.enabled' => true,
        ]);

        $this->registry = app(ProviderRegistry::class);
        $this->registry->flush();
        $this->registry->registerServer(app(StubServerProvider::class));
        $this->registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_sync_infrastructure_schema_and_services_are_ready(): void
    {
        foreach (['sync_logs', 'admin_notifications'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }

        foreach ([
            ServiceSyncService::class,
            ServiceSyncComparisonService::class,
            ServiceSyncResolutionService::class,
            NodeSyncService::class,
            SyncLogService::class,
            SyncLogQueryService::class,
        ] as $service) {
            $this->assertSame(app($service), app($service), "Expected {$service} to be a singleton.");
        }

        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString(ServiceSyncJob::class, $output);
        $this->assertStringContainsString(NodeSyncJob::class, $output);
    }

    public function test_service_sync_detects_ip_and_status_divergence_with_logs_and_alerts(): void
    {
        $this->registry->registerServer(new class implements ServerProviderInterface
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
                    ipAddress: '203.0.113.44',
                    payload: ['remote_status' => 'suspended'],
                );
            }
        });

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-e2e-diverged',
            'ip_address' => '10.0.0.8',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-e2e-diverged');

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(0, $result->polled);
        $this->assertSame(1, $result->diverged);
        $this->assertSame(ServiceStatus::Active, $service->fresh()->status);
        $this->assertSame('10.0.0.8', $service->fresh()->ip_address);

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Service->value,
            'subject_id' => $service->id,
            'outcome' => SyncLogOutcome::Diverged->value,
        ]);

        $log = SyncLog::query()
            ->where('subject_id', $service->id)
            ->where('outcome', SyncLogOutcome::Diverged)
            ->first();

        $this->assertNotNull($log);
        $divergenceTypes = collect($log->divergences ?? [])->pluck('type')->all();
        $this->assertContains('ip_changed', $divergenceTypes);
        $this->assertContains('status_mismatch', $divergenceTypes);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => SyncAlertService::TYPE_SERVICE_DIVERGED,
            'dedupe_key' => 'sync.service.'.$service->id,
            'variant' => 'warning',
        ]);

        $this->assertSame(1, app(AdminNotificationFeed::class)->unreadCount());
    }

    public function test_external_deletion_terminates_local_service_and_clears_divergence_alert(): void
    {
        config(['corepanel.services.sync.resolve.enabled' => true]);

        $this->registry->registerServer(new class implements ServerProviderInterface
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
            'external_id' => 'stub-e2e-gone',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-e2e-gone');

        app(AdminNotificationService::class)->upsert(
            type: SyncAlertService::TYPE_SERVICE_DIVERGED,
            dedupeKey: 'sync.service.'.$service->id,
            title: __('Service sync divergence'),
            message: __('Stale divergence alert'),
            variant: 'warning',
        );

        $result = app(ServiceSyncService::class)->pollAll();

        $this->assertSame(1, $result->resolved);
        $this->assertSame(0, $result->diverged);
        $this->assertSame(ServiceStatus::Terminated, $service->fresh()->status);
        $this->assertNotNull($service->fresh()->terminated_at);

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Service->value,
            'subject_id' => $service->id,
            'outcome' => SyncLogOutcome::Resolved->value,
        ]);

        $notification = AdminNotification::query()
            ->where('dedupe_key', 'sync.service.'.$service->id)
            ->first();

        $this->assertNotNull($notification?->read_at);
    }

    public function test_admin_manual_service_sync_records_history_and_surfaces_in_sync_log_ui(): void
    {
        config(['corepanel.services.sync.resolve.enabled' => true]);

        $admin = User::factory()->withRole('admin')->create();

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-e2e-manual',
            'hostname' => 'manual-sync.example.test',
            'ip_address' => '10.255.0.20',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-e2e-manual');

        $this->actingAs($admin)
            ->post(route('admin.services.sync', $service))
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Service->value,
            'subject_id' => $service->id,
            'outcome' => SyncLogOutcome::Polled->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sync-logs.index'))
            ->assertOk()
            ->assertSee(__('Sync history'))
            ->assertSee('manual-sync.example.test');
    }

    public function test_admin_run_sync_from_history_page_covers_services_and_nodes(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-e2e-run-all',
            'hostname' => 'run-all-sync.example.test',
            'ip_address' => '10.255.0.21',
        ]);

        Node::factory()->forModule('stub')->create([
            'hostname' => 'node-run-all-sync.example.test',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-e2e-run-all');

        $this->actingAs($admin)
            ->post(route('admin.sync.run'))
            ->assertRedirect(route('admin.sync-logs.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Service->value,
            'subject_id' => $service->id,
        ]);

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Node->value,
            'outcome' => SyncLogOutcome::Synced->value,
        ]);
    }

    public function test_divergence_alert_appears_in_admin_topbar_after_automatic_poll(): void
    {
        $this->registry->registerServer(new class implements ServerProviderInterface
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
                    ipAddress: '203.0.113.55',
                    payload: ['remote_status' => 'active'],
                );
            }
        });

        $admin = User::factory()->withRole('admin')->create();

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-e2e-alert',
            'ip_address' => '10.0.0.9',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-e2e-alert');

        app(ServiceSyncService::class)->pollAll();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Service sync divergence'), false)
            ->assertSee('data-notification="sync.service.'.$service->id.'"', false)
            ->assertSee(__('View sync history'), false);
    }
}
