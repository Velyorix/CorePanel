<?php

namespace Tests\Feature\Automation;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\Services\AutomationEventBus;
use Core\Automation\Support\AutomationEvent;
use Core\Billing\Events\InvoicePaid;
use Core\Billing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationEventBusTest extends TestCase
{
    use RefreshDatabase;

    private AutomationEventBus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.automation.enabled' => true,
            'corepanel.api.webhooks.enabled' => false,
        ]);

        $this->bus = app(AutomationEventBus::class);
        $this->bus->flush();
    }

    public function test_bus_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(AutomationEventBus::class),
            app(AutomationEventBus::class),
        );
    }

    public function test_dynamic_listeners_receive_dispatched_events_in_priority_order(): void
    {
        $order = [];

        $this->bus->listen(AutomationEvent::INVOICE_PAID, function (AutomationEventContext $context) use (&$order): void {
            $order[] = 'second';
            $this->assertSame(42, $context->data['invoice_id'] ?? null);
        }, priority: 20);

        $this->bus->listen(AutomationEvent::INVOICE_PAID, function () use (&$order): void {
            $order[] = 'first';
        }, priority: 10);

        $this->bus->dispatch(AutomationEvent::INVOICE_PAID, [
            'invoice_id' => 42,
        ]);

        $this->assertSame(['first', 'second'], $order);
        $this->assertSame(2, $this->bus->listenerCount(AutomationEvent::INVOICE_PAID));
    }

    public function test_forget_removes_a_dynamic_listener(): void
    {
        $hits = 0;

        $id = $this->bus->listen(AutomationEvent::SERVICE_CREATED, function () use (&$hits): void {
            $hits++;
        });

        $this->bus->forget($id);
        $this->bus->dispatch(AutomationEvent::SERVICE_CREATED, ['service_id' => 1]);

        $this->assertSame(0, $hits);
        $this->assertFalse($this->bus->hasListeners(AutomationEvent::SERVICE_CREATED));
    }

    public function test_domain_event_is_bridged_into_the_bus(): void
    {
        $this->bus->flush();

        $received = null;

        $this->bus->listen(AutomationEvent::INVOICE_PAID, function (AutomationEventContext $context) use (&$received): void {
            $received = $context;
        });

        $invoice = Invoice::factory()->unpaid()->create([
            'total_amount' => '15.00',
            'subtotal' => '15.00',
        ]);

        event(new InvoicePaid($invoice->fresh() ?? $invoice));

        $this->assertInstanceOf(AutomationEventContext::class, $received);
        $this->assertSame(AutomationEvent::INVOICE_PAID, $received->event);
        $this->assertSame($invoice->id, $received->data['invoice_id'] ?? null);
        $this->assertSame($invoice->client_id, $received->data['client_id'] ?? null);
        $this->assertInstanceOf(InvoicePaid::class, $received->source);
    }

    public function test_disabled_automation_skips_listeners(): void
    {
        config(['corepanel.automation.enabled' => false]);

        $hits = 0;
        $this->bus->listen(AutomationEvent::NODE_OFFLINE, function () use (&$hits): void {
            $hits++;
        });

        $this->bus->dispatch(AutomationEvent::NODE_OFFLINE, ['node_id' => 1]);

        $this->assertSame(0, $hits);
    }

    public function test_listener_exceptions_do_not_block_other_listeners(): void
    {
        $hits = [];

        $this->bus->listen(AutomationEvent::TICKET_CREATED, function () use (&$hits): void {
            $hits[] = 'a';
            throw new \RuntimeException('boom');
        }, priority: 1);

        $this->bus->listen(AutomationEvent::TICKET_CREATED, function () use (&$hits): void {
            $hits[] = 'b';
        }, priority: 2);

        $this->bus->dispatch(AutomationEvent::TICKET_CREATED, ['ticket_id' => 9]);

        $this->assertSame(['a', 'b'], $hits);
    }
}
