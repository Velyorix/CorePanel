<?php

namespace Tests\Feature\Billing;

use Carbon\Carbon;
use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\Enums\OverdueInvoiceAction;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Billing\Services\ClientCreditService;
use Core\Billing\Services\OverdueSuspensionService;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueSuspensionServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{action: string, service_id: int, invoice_id: int}> */
    private array $actionLog = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actionLog = [];

        $fake = new class($this->actionLog) implements OverdueServiceActions
        {
            /** @param list<array{action: string, service_id: int, invoice_id: int}> $log */
            public function __construct(private array &$log)
            {
            }

            public function suspend(Invoice $invoice, int $serviceId, string $reason): void
            {
                $this->log[] = [
                    'action' => 'suspend',
                    'service_id' => $serviceId,
                    'invoice_id' => $invoice->id,
                ];
            }

            public function terminate(Invoice $invoice, int $serviceId, string $reason): void
            {
                $this->log[] = [
                    'action' => 'terminate',
                    'service_id' => $serviceId,
                    'invoice_id' => $invoice->id,
                ];
            }
        };

        $this->app->instance(OverdueServiceActions::class, $fake);

        config([
            'corepanel.billing.suspension.enabled' => true,
            'corepanel.billing.suspension.suspend_after_days' => 3,
            'corepanel.billing.suspension.terminate_after_days' => 7,
            'corepanel.billing.suspension.skip_if_credit_available' => true,
            'corepanel.billing.suspension.skip_vip_clients' => true,
        ]);
    }

    public function test_service_and_null_actions_are_registered(): void
    {
        $this->assertSame(
            app(OverdueSuspensionService::class),
            app(OverdueSuspensionService::class),
        );
        $this->assertInstanceOf(OverdueServiceActions::class, app(OverdueServiceActions::class));
    }

    public function test_disabled_config_skips_processing(): void
    {
        config(['corepanel.billing.suspension.enabled' => false]);

        $this->makeOverdueInvoice(serviceId: 10, dueAt: '2026-01-01');

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-10'));

        $this->assertSame(0, $result->suspended);
        $this->assertSame(0, $result->terminated);
        $this->assertSame([], $this->actionLog);
    }

    public function test_suspends_at_t_plus_3_and_is_idempotent(): void
    {
        $invoice = $this->makeOverdueInvoice(serviceId: 42, dueAt: '2026-01-01');

        $service = app(OverdueSuspensionService::class);
        $first = $service->process(Carbon::parse('2026-01-04'));

        $this->assertSame(1, $first->suspended);
        $this->assertSame(OverdueInvoiceAction::Suspended, $invoice->fresh()->overdue_action);
        $this->assertNotNull($invoice->fresh()->overdue_action_at);
        $this->assertSame([
            ['action' => 'suspend', 'service_id' => 42, 'invoice_id' => $invoice->id],
        ], $this->actionLog);

        array_splice($this->actionLog, 0);
        $second = $service->process(Carbon::parse('2026-01-04'));

        $this->assertSame(0, $second->suspended);
        $this->assertSame(1, $second->skipped);
        $this->assertSame([], $this->actionLog);
    }

    public function test_terminates_at_t_plus_7(): void
    {
        $invoice = $this->makeOverdueInvoice(serviceId: 7, dueAt: '2026-01-01');

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-08'));

        $this->assertSame(0, $result->suspended);
        $this->assertSame(1, $result->terminated);
        $this->assertSame(OverdueInvoiceAction::Terminated, $invoice->fresh()->overdue_action);
        $this->assertSame([
            ['action' => 'terminate', 'service_id' => 7, 'invoice_id' => $invoice->id],
        ], $this->actionLog);
    }

    public function test_escalates_from_suspended_to_terminated(): void
    {
        $invoice = $this->makeOverdueInvoice(serviceId: 99, dueAt: '2026-01-01', overdueAction: OverdueInvoiceAction::Suspended);

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-08'));

        $this->assertSame(1, $result->terminated);
        $this->assertSame(OverdueInvoiceAction::Terminated, $invoice->fresh()->overdue_action);
        $this->assertSame('terminate', $this->actionLog[0]['action']);
    }

    public function test_skips_when_client_has_credit_balance(): void
    {
        $client = Client::factory()->create(['credit_balance' => '25.00']);
        $invoice = $this->makeOverdueInvoice(serviceId: 5, dueAt: '2026-01-01', client: $client);

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-04'));

        $this->assertSame(1, $result->skipped);
        $this->assertNull($invoice->fresh()->overdue_action);
        $this->assertSame([], $this->actionLog);
        $this->assertSame('25.00', app(ClientCreditService::class)->balance($client));
    }

    public function test_skips_invoices_without_service_ids(): void
    {
        $this->makeOverdueInvoice(serviceId: null, dueAt: '2026-01-01');

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-04'));

        $this->assertSame(1, $result->skipped);
        $this->assertSame([], $this->actionLog);
    }

    public function test_skips_before_suspend_threshold(): void
    {
        $this->makeOverdueInvoice(serviceId: 3, dueAt: '2026-01-01');

        $result = app(OverdueSuspensionService::class)->process(Carbon::parse('2026-01-02'));

        $this->assertSame(1, $result->skipped);
        $this->assertSame([], $this->actionLog);
    }

    public function test_overdue_action_enum_values(): void
    {
        $this->assertSame(['suspended', 'terminated'], OverdueInvoiceAction::values());
    }

    private function makeOverdueInvoice(
        ?int $serviceId,
        string $dueAt,
        ?Client $client = null,
        ?OverdueInvoiceAction $overdueAction = null,
    ): Invoice {
        $invoice = Invoice::factory()->overdue()->create([
            'client_id' => ($client ?? Client::factory()->create(['credit_balance' => '0.00']))->id,
            'total_amount' => '50.00',
            'subtotal' => '50.00',
            'due_at' => Carbon::parse($dueAt),
            'overdue_action' => $overdueAction,
            'overdue_action_at' => $overdueAction !== null ? now()->subDay() : null,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'service_id' => $serviceId,
            'line_total' => '50.00',
            'unit_price' => '50.00',
            'setup_fee' => '0.00',
            'tax_amount' => '0.00',
        ]);

        return $invoice->fresh(['items', 'client']) ?? $invoice;
    }
}
