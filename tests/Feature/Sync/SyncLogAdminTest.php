<?php

namespace Tests\Feature\Sync;

use App\Models\User;
use Core\Nodes\Models\Node;
use Core\Providers\Contracts\ServerProviderInterface;
use Core\Providers\DataTransferObjects\ProvisioningRequest;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Providers\Services\ProviderRegistry;
use Core\Providers\Stubs\StubNodeProvider;
use Core\Providers\Stubs\StubServerProvider;
use Core\Provisioning\Services\ProviderResourceMappingService;
use Core\Services\Models\Service;
use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Core\Sync\Models\SyncLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncLogAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.services.sync.enabled' => true,
            'corepanel.services.sync.resolve.enabled' => false,
            'corepanel.sync.logs.service.enabled' => true,
            'corepanel.sync.logs.node.enabled' => true,
            'corepanel.sync.alerts.enabled' => true,
            'corepanel.provisioning.stub.enabled' => true,
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.sync-admin',
        ]);

        $registry = app(ProviderRegistry::class);
        $registry->flush();
        $registry->registerServer(app(StubServerProvider::class));
        $registry->registerNode(app(StubNodeProvider::class));
    }

    public function test_admin_can_view_sync_history_index(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        SyncLog::query()->create([
            'subject_type' => SyncLogSubject::Service,
            'subject_id' => 1,
            'module' => 'stub',
            'outcome' => SyncLogOutcome::Diverged,
            'message' => 'IP mismatch',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sync-logs.index'))
            ->assertOk()
            ->assertSee(__('Sync history'))
            ->assertSee('IP mismatch')
            ->assertSee(__('Run sync now'));
    }

    public function test_admin_can_view_sync_log_details(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $log = SyncLog::query()->create([
            'subject_type' => SyncLogSubject::Service,
            'subject_id' => 1,
            'module' => 'stub',
            'outcome' => SyncLogOutcome::Failed,
            'message' => 'Remote API unavailable.',
            'divergences' => [['type' => 'ip_changed']],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sync-logs.show', $log))
            ->assertOk()
            ->assertSee(__('Sync log #:id', ['id' => $log->id]))
            ->assertSee('Remote API unavailable.')
            ->assertSee('ip_changed');
    }

    public function test_admin_can_trigger_manual_service_sync_from_service_page(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-manual',
            'hostname' => 'manual-sync.example.test',
            'ip_address' => '10.255.0.11',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-manual');

        $this->actingAs($admin)
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee(__('Sync with provider'));

        $this->actingAs($admin)
            ->post(route('admin.services.sync', $service))
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sync_logs', [
            'subject_type' => SyncLogSubject::Service->value,
            'subject_id' => $service->id,
            'outcome' => SyncLogOutcome::Polled->value,
        ]);
    }

    public function test_admin_can_run_full_sync_from_sync_history_page(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-run-all',
            'hostname' => 'run-all.example.test',
            'ip_address' => '10.255.0.12',
        ]);

        Node::factory()->forModule('stub')->create([
            'hostname' => 'node-run-all.example.test',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-run-all');

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

    public function test_support_cannot_run_manual_sync(): void
    {
        $support = User::factory()->withRole('support')->create();

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-blocked',
        ]);

        app(ProviderResourceMappingService::class)->upsert($service, 'stub-blocked');

        $this->actingAs($support)
            ->post(route('admin.services.sync', $service))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.sync.run'))
            ->assertForbidden();
    }

    public function test_service_show_lists_recent_sync_logs(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $service = Service::factory()->active()->create([
            'module' => 'stub',
            'external_id' => 'stub-history',
        ]);

        SyncLog::query()->create([
            'subject_type' => SyncLogSubject::Service,
            'subject_id' => $service->id,
            'module' => 'stub',
            'outcome' => SyncLogOutcome::Diverged,
            'message' => 'Status mismatch detected',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee(__('Sync history'))
            ->assertSee('Status mismatch detected');
    }
}
