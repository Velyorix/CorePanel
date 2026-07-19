<?php

namespace Tests\Feature\Services;

use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\Enums\OverdueInvoiceAction;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Billing\Services\LifecycleOverdueServiceActions;
use Core\Billing\Services\OverdueSuspensionService;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Core\Services\Services\ServiceControlService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ServiceControlServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceControlService $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->control = app(ServiceControlService::class);

        config([
            'corepanel.billing.suspension.enabled' => true,
            'corepanel.billing.suspension.suspend_after_days' => 3,
            'corepanel.billing.suspension.terminate_after_days' => 7,
            'corepanel.billing.suspension.skip_if_credit_available' => false,
            'corepanel.billing.suspension.skip_vip_clients' => false,
        ]);
    }

    public function test_control_service_and_overdue_adapter_are_registered(): void
    {
        $this->assertSame(
            app(ServiceControlService::class),
            app(ServiceControlService::class),
        );
        $this->assertInstanceOf(LifecycleOverdueServiceActions::class, app(OverdueServiceActions::class));
    }

    public function test_action_matrix_matches_status_rules(): void
    {
        $this->assertSame(
            [
                ServiceAction::Start,
                ServiceAction::Stop,
                ServiceAction::Restart,
                ServiceAction::Suspend,
                ServiceAction::Terminate,
                ServiceAction::Reinstall,
            ],
            ServiceAction::allowedFor(ServiceStatus::Active),
        );
        $this->assertSame(
            [ServiceAction::Unsuspend, ServiceAction::Terminate],
            ServiceAction::allowedFor(ServiceStatus::Suspended),
        );
        $this->assertSame([], ServiceAction::allowedFor(ServiceStatus::Pending));
        $this->assertTrue(ServiceAction::Suspend->isAllowedFor(ServiceStatus::Active));
        $this->assertFalse(ServiceAction::Start->isAllowedFor(ServiceStatus::Suspended));
    }

    public function test_lifecycle_actions_suspend_unsuspend_and_terminate(): void
    {
        $service = Service::factory()->active()->create();

        $suspended = $this->control->suspend($service);
        $this->assertSame(ServiceStatus::Suspended, $suspended->status);
        $this->assertNotNull($suspended->suspended_at);

        $active = $this->control->unsuspend($suspended);
        $this->assertSame(ServiceStatus::Active, $active->status);
        $this->assertNull($active->suspended_at);

        $terminated = $this->control->terminate($active);
        $this->assertSame(ServiceStatus::Terminated, $terminated->status);
        $this->assertNotNull($terminated->terminated_at);

        $this->assertSame(3, ServiceActionLog::query()->where('service_id', $service->id)->count());
    }

    public function test_power_actions_keep_active_status_and_log_skipped_module(): void
    {
        $service = Service::factory()->active()->create(['module' => 'pterodactyl']);

        foreach ([ServiceAction::Start, ServiceAction::Stop, ServiceAction::Restart, ServiceAction::Reinstall] as $action) {
            $result = $this->control->execute($service->fresh() ?? $service, $action);
            $this->assertSame(ServiceStatus::Active, $result->status);
        }

        $logs = ServiceActionLog::query()->where('service_id', $service->id)->orderBy('id')->get();
        $this->assertCount(4, $logs);
        $this->assertTrue($logs->every(fn (ServiceActionLog $log): bool => $log->status === ServiceActionLogStatus::Skipped));
        $this->assertSame(
            [
                ServiceAction::Start,
                ServiceAction::Stop,
                ServiceAction::Restart,
                ServiceAction::Reinstall,
            ],
            $logs->pluck('action')->all(),
        );
    }

    public function test_illegal_action_throws(): void
    {
        $service = Service::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Action [start] is not allowed for service status [pending].');

        $this->control->start($service);
    }

    public function test_lifecycle_actions_are_idempotent(): void
    {
        $service = Service::factory()->suspended()->create();

        $again = $this->control->suspend($service);
        $this->assertSame(ServiceStatus::Suspended, $again->status);
        $this->assertSame(ServiceActionLogStatus::Skipped, ServiceActionLog::query()->latest('id')->firstOrFail()->status);

        $terminated = Service::factory()->terminated()->create();
        $this->control->terminate($terminated);
        $this->assertSame(ServiceStatus::Terminated, $terminated->fresh()->status);
    }

    public function test_overdue_automation_suspends_and_terminates_real_services(): void
    {
        $service = Service::factory()->active()->create();
        $invoice = Invoice::factory()->overdue()->create([
            'client_id' => $service->client_id,
            'total_amount' => '50.00',
            'subtotal' => '50.00',
            'due_at' => Carbon::parse('2026-01-01'),
            'overdue_action' => null,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'service_id' => $service->id,
            'line_total' => '50.00',
            'unit_price' => '50.00',
            'setup_fee' => '0.00',
            'tax_amount' => '0.00',
        ]);

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-04'));

        $this->assertSame(1, $result->suspended);
        $this->assertSame(OverdueInvoiceAction::Suspended, $invoice->fresh()->overdue_action);
        $this->assertSame(ServiceStatus::Suspended, $service->fresh()->status);

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-08'));

        $this->assertSame(1, $result->terminated);
        $this->assertSame(OverdueInvoiceAction::Terminated, $invoice->fresh()->overdue_action);
        $this->assertSame(ServiceStatus::Terminated, $service->fresh()->status);
    }
}
