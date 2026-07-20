<?php

namespace Tests\Feature\Services;

use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ServiceLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = app(ServiceLifecycleService::class);
    }

    public function test_service_lifecycle_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ServiceLifecycleService::class),
            app(ServiceLifecycleService::class),
        );
    }

    public function test_status_allowed_transitions_match_lifecycle_graph(): void
    {
        $this->assertSame(
            [ServiceStatus::Provisioning, ServiceStatus::Cancelled],
            ServiceStatus::Pending->allowedTransitions(),
        );
        $this->assertSame(
            [ServiceStatus::Active, ServiceStatus::Failed, ServiceStatus::Cancelled],
            ServiceStatus::Provisioning->allowedTransitions(),
        );
        $this->assertSame(
            [ServiceStatus::Suspended, ServiceStatus::Terminated],
            ServiceStatus::Active->allowedTransitions(),
        );
        $this->assertSame(
            [ServiceStatus::Active, ServiceStatus::Terminated],
            ServiceStatus::Suspended->allowedTransitions(),
        );
        $this->assertSame(
            [ServiceStatus::Provisioning, ServiceStatus::Cancelled],
            ServiceStatus::Failed->allowedTransitions(),
        );
        $this->assertSame([], ServiceStatus::Terminated->allowedTransitions());
        $this->assertSame([], ServiceStatus::Cancelled->allowedTransitions());

        $this->assertTrue(ServiceStatus::Pending->canTransitionTo(ServiceStatus::Provisioning));
        $this->assertFalse(ServiceStatus::Pending->canTransitionTo(ServiceStatus::Active));
        $this->assertFalse(ServiceStatus::Active->canTransitionTo(ServiceStatus::Cancelled));
        $this->assertTrue(ServiceStatus::Terminated->isTerminal());
        $this->assertTrue(ServiceStatus::Active->isBillable());
        $this->assertFalse(ServiceStatus::Pending->isBillable());
    }

    public function test_happy_path_pending_to_active_to_suspended_to_terminated(): void
    {
        $service = Service::factory()->create();

        $provisioning = $this->lifecycle->markProvisioning($service);
        $this->assertSame(ServiceStatus::Provisioning, $provisioning->status);

        $active = $this->lifecycle->markActive($provisioning);
        $this->assertSame(ServiceStatus::Active, $active->status);
        $this->assertNotNull($active->provisioned_at);
        $this->assertNotNull($active->started_at);
        $this->assertNull($active->suspended_at);

        $suspended = $this->lifecycle->suspend($active);
        $this->assertSame(ServiceStatus::Suspended, $suspended->status);
        $this->assertNotNull($suspended->suspended_at);

        $unsuspended = $this->lifecycle->unsuspend($suspended);
        $this->assertSame(ServiceStatus::Active, $unsuspended->status);
        $this->assertNull($unsuspended->suspended_at);

        $terminated = $this->lifecycle->terminate($unsuspended);
        $this->assertSame(ServiceStatus::Terminated, $terminated->status);
        $this->assertNotNull($terminated->terminated_at);
        $this->assertNotNull($terminated->ended_at);
    }

    public function test_provisioning_can_fail_and_retry(): void
    {
        $service = $this->lifecycle->markProvisioning(Service::factory()->create());

        $failed = $this->lifecycle->markFailed($service);
        $this->assertSame(ServiceStatus::Failed, $failed->status);
        $this->assertNull($failed->ended_at);

        $retry = $this->lifecycle->markProvisioning($failed);
        $this->assertSame(ServiceStatus::Provisioning, $retry->status);

        $active = $this->lifecycle->markActive($retry);
        $this->assertSame(ServiceStatus::Active, $active->status);
    }

    public function test_pending_and_provisioning_can_be_cancelled(): void
    {
        $pending = $this->lifecycle->cancel(Service::factory()->create());
        $this->assertSame(ServiceStatus::Cancelled, $pending->status);
        $this->assertNotNull($pending->ended_at);

        $provisioning = $this->lifecycle->markProvisioning(Service::factory()->create());
        $cancelled = $this->lifecycle->cancel($provisioning);
        $this->assertSame(ServiceStatus::Cancelled, $cancelled->status);
    }

    public function test_failed_service_can_be_cancelled(): void
    {
        $failed = $this->lifecycle->markFailed(
            $this->lifecycle->markProvisioning(Service::factory()->create()),
        );

        $cancelled = $this->lifecycle->cancel($failed);

        $this->assertSame(ServiceStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->ended_at);
    }

    public function test_transitions_are_idempotent(): void
    {
        $service = $this->lifecycle->markActive(
            $this->lifecycle->markProvisioning(Service::factory()->create()),
        );
        $provisionedAt = $service->provisioned_at?->toISOString();
        $startedAt = $service->started_at?->toISOString();

        $again = $this->lifecycle->markActive($service);
        $this->assertSame($provisionedAt, $again->provisioned_at?->toISOString());
        $this->assertSame($startedAt, $again->started_at?->toISOString());

        $suspended = $this->lifecycle->suspend($again);
        $suspendedAt = $suspended->suspended_at?->toISOString();
        $suspendedAgain = $this->lifecycle->suspend($suspended);
        $this->assertSame($suspendedAt, $suspendedAgain->suspended_at?->toISOString());

        $cancelled = $this->lifecycle->cancel(Service::factory()->create());
        $endedAt = $cancelled->ended_at?->toISOString();
        $cancelledAgain = $this->lifecycle->cancel($cancelled);
        $this->assertSame($endedAt, $cancelledAgain->ended_at?->toISOString());
    }

    public function test_illegal_transitions_throw(): void
    {
        $pending = Service::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only provisioning or suspended services can become active.');

        $this->lifecycle->markActive($pending);
    }

    public function test_active_service_cannot_be_cancelled(): void
    {
        $active = Service::factory()->active()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only pending, provisioning, or failed services can be cancelled.');

        $this->lifecycle->cancel($active);
    }

    public function test_terminated_service_cannot_transition(): void
    {
        $terminated = Service::factory()->terminated()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only active services can be suspended.');

        $this->lifecycle->suspend($terminated);
    }

    public function test_factory_states_match_status_enum(): void
    {
        $this->assertSame(ServiceStatus::Pending, Service::factory()->create()->status);
        $this->assertSame(ServiceStatus::Provisioning, Service::factory()->provisioning()->create()->status);
        $this->assertSame(ServiceStatus::Active, Service::factory()->active()->create()->status);
        $this->assertSame(ServiceStatus::Suspended, Service::factory()->suspended()->create()->status);
        $this->assertSame(ServiceStatus::Failed, Service::factory()->failed()->create()->status);
        $this->assertSame(ServiceStatus::Terminated, Service::factory()->terminated()->create()->status);
        $this->assertSame(ServiceStatus::Cancelled, Service::factory()->cancelled()->create()->status);
    }
}
