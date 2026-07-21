<?php

namespace Tests\Feature\Provisioning;

use App\Models\User;
use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Provisioning\Services\ProvisioningDeadLetterService;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminProvisioningDeadLetterReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => false,
            'corepanel.rbac.permissions_registry.cache_enabled' => false,
            'corepanel.rbac.cache.store' => 'array',
        ]);
    }

    public function test_admin_can_list_and_view_pending_dead_letters(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $service = Service::factory()->failed()->create(['module' => 'stub']);
        $letter = app(ProvisioningDeadLetterService::class)->record($service->id, attempts: 2);

        $this->actingAs($admin)
            ->get(route('admin.provisioning-dead-letters.index'))
            ->assertOk()
            ->assertSee(__('Provisioning failures'))
            ->assertSee('#'.$letter->id);

        $this->actingAs($admin)
            ->get(route('admin.provisioning-dead-letters.show', $letter))
            ->assertOk()
            ->assertSee(__('Requeue provisioning'));
    }

    public function test_support_can_view_but_not_requeue(): void
    {
        $support = User::factory()->withRole('support')->create();
        $service = Service::factory()->failed()->create(['module' => 'stub']);
        $letter = app(ProvisioningDeadLetterService::class)->record($service->id);

        $this->actingAs($support)
            ->get(route('admin.provisioning-dead-letters.show', $letter))
            ->assertOk()
            ->assertDontSee(__('Requeue provisioning'));

        $this->actingAs($support)
            ->post(route('admin.provisioning-dead-letters.requeue', $letter))
            ->assertForbidden();
    }

    public function test_admin_can_resolve_and_discard(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $service = Service::factory()->failed()->create(['module' => 'stub']);
        $letter = app(ProvisioningDeadLetterService::class)->record($service->id);

        $this->actingAs($admin)
            ->post(route('admin.provisioning-dead-letters.resolve', $letter), [
                'notes' => 'Handled manually',
            ])
            ->assertRedirect(route('admin.provisioning-dead-letters.show', $letter));

        $this->assertSame(ProvisioningDeadLetterStatus::Resolved, $letter->fresh()->status);
        $this->assertSame('Handled manually', $letter->fresh()->resolution_notes);

        $other = app(ProvisioningDeadLetterService::class)->record(
            Service::factory()->failed()->create(['module' => 'stub'])->id,
        );

        $this->actingAs($admin)
            ->post(route('admin.provisioning-dead-letters.discard', $other), [
                'notes' => 'Cancelled',
            ])
            ->assertRedirect(route('admin.provisioning-dead-letters.show', $other));

        $this->assertSame(ProvisioningDeadLetterStatus::Discarded, $other->fresh()->status);
    }

    public function test_admin_can_requeue_from_review_ui(): void
    {
        Queue::fake();

        $admin = User::factory()->withRole('admin')->create();
        $service = Service::factory()->failed()->create([
            'module' => 'stub',
            'status' => ServiceStatus::Failed,
        ]);
        $letter = app(ProvisioningDeadLetterService::class)->record($service->id);

        $this->actingAs($admin)
            ->post(route('admin.provisioning-dead-letters.requeue', $letter), [
                'notes' => 'Retry after fix',
                'release_node' => '1',
            ])
            ->assertRedirect(route('admin.provisioning-dead-letters.show', $letter))
            ->assertSessionHas('status');

        $this->assertSame(ProvisioningDeadLetterStatus::Requeued, $letter->fresh()->status);
        Queue::assertPushed(\Core\Provisioning\Jobs\ProvisionServiceJob::class);
    }

    public function test_guest_cannot_access_dead_letter_review(): void
    {
        $letter = ProvisioningDeadLetter::query()->create([
            'service_id' => Service::factory()->failed()->create()->id,
            'status' => ProvisioningDeadLetterStatus::PendingReview,
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $this->get(route('admin.provisioning-dead-letters.index'))
            ->assertRedirect();

        $this->get(route('admin.provisioning-dead-letters.show', $letter))
            ->assertRedirect();
    }
}
