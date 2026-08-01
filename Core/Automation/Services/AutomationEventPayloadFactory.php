<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\Support\AutomationEvent;
use Core\Billing\Events\InvoiceOverdue;
use Core\Billing\Events\InvoicePaid;
use Core\Nodes\Events\NodeCameOnline;
use Core\Nodes\Events\NodeWentOffline;
use Core\Services\Events\ServiceCreated;
use Core\Services\Events\ServiceSuspended;
use Core\Services\Events\ServiceTerminated;
use Core\Tickets\Events\TicketCreated;
use Core\Tickets\Events\TicketReplied;

/**
 * Build a normalized automation context from a Laravel domain event.
 */
class AutomationEventPayloadFactory
{
    public function fromDomainEvent(string $alias, object $event): AutomationEventContext
    {
        return AutomationEventContext::make(
            event: $alias,
            data: $this->dataFor($alias, $event),
            source: $event,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFor(string $alias, object $event): array
    {
        return match (true) {
            $event instanceof ServiceCreated => [
                'service_id' => $event->service->id,
                'client_id' => $event->service->client_id,
                'product_id' => $event->service->product_id,
                'status' => $event->service->status?->value ?? $event->service->status,
                'hostname' => $event->service->hostname,
            ],
            $event instanceof ServiceSuspended => [
                'service_id' => $event->service->id,
                'client_id' => $event->service->client_id,
                'status' => $event->service->status?->value ?? $event->service->status,
            ],
            $event instanceof ServiceTerminated => [
                'service_id' => $event->service->id,
                'client_id' => $event->service->client_id,
                'status' => $event->service->status?->value ?? $event->service->status,
            ],
            $event instanceof InvoicePaid => [
                'invoice_id' => $event->invoice->id,
                'client_id' => $event->invoice->client_id,
                'invoice_number' => $event->invoice->invoice_number,
                'amount' => $event->invoice->total_amount,
                'currency' => $event->invoice->currency,
            ],
            $event instanceof InvoiceOverdue => [
                'invoice_id' => $event->invoice->id,
                'client_id' => $event->invoice->client_id,
                'invoice_number' => $event->invoice->invoice_number,
                'amount' => $event->invoice->total_amount,
                'due_at' => $event->invoice->due_at?->toIso8601String(),
            ],
            $event instanceof TicketCreated => [
                'ticket_id' => $event->ticket->id,
                'client_id' => $event->ticket->client_id,
                'ticket_number' => $event->ticket->ticket_number,
                'subject' => $event->ticket->subject,
                'status' => $event->ticket->status?->value ?? $event->ticket->status,
                'priority' => $event->ticket->priority?->value ?? $event->ticket->priority,
            ],
            $event instanceof TicketReplied => [
                'ticket_id' => $event->ticket->id,
                'client_id' => $event->ticket->client_id,
                'ticket_number' => $event->ticket->ticket_number,
                'message_id' => $event->message->id,
                'author_id' => $event->author->id,
            ],
            $event instanceof NodeWentOffline => [
                'node_id' => $event->node->id,
                'name' => $event->node->name,
                'hostname' => $event->node->hostname,
                'status' => $event->node->status?->value ?? $event->node->status,
            ],
            $event instanceof NodeCameOnline => [
                'node_id' => $event->node->id,
                'name' => $event->node->name,
                'hostname' => $event->node->hostname,
                'status' => $event->node->status?->value ?? $event->node->status,
            ],
            default => [
                'alias' => $alias,
                'event_class' => $event::class,
            ],
        };
    }

    /**
     * @return array<string, class-string>
     */
    public static function defaultDomainMap(): array
    {
        return [
            AutomationEvent::SERVICE_CREATED => ServiceCreated::class,
            AutomationEvent::SERVICE_SUSPENDED => ServiceSuspended::class,
            AutomationEvent::SERVICE_TERMINATED => ServiceTerminated::class,
            AutomationEvent::INVOICE_PAID => InvoicePaid::class,
            AutomationEvent::INVOICE_OVERDUE => InvoiceOverdue::class,
            AutomationEvent::TICKET_CREATED => TicketCreated::class,
            AutomationEvent::TICKET_REPLIED => TicketReplied::class,
            AutomationEvent::NODE_OFFLINE => NodeWentOffline::class,
            AutomationEvent::NODE_ONLINE => NodeCameOnline::class,
        ];
    }
}
