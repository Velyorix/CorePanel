<?php

namespace Core\Webhooks\Listeners;

use Core\Billing\Events\InvoiceOverdue;
use Core\Billing\Events\InvoicePaid;
use Core\Nodes\Events\NodeCameOnline;
use Core\Nodes\Events\NodeWentOffline;
use Core\Services\Events\ServiceCreated;
use Core\Services\Events\ServiceSuspended;
use Core\Services\Events\ServiceTerminated;
use Core\Tickets\Events\TicketCreated;
use Core\Tickets\Events\TicketReplied;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Services\WebhookDispatcher;
use Illuminate\Events\Dispatcher;

class DispatchOutgoingWebhooks
{
    public function __construct(
        private readonly WebhookDispatcher $webhooks,
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ServiceCreated::class, [self::class, 'onServiceCreated']);
        $events->listen(ServiceSuspended::class, [self::class, 'onServiceSuspended']);
        $events->listen(ServiceTerminated::class, [self::class, 'onServiceTerminated']);
        $events->listen(InvoicePaid::class, [self::class, 'onInvoicePaid']);
        $events->listen(InvoiceOverdue::class, [self::class, 'onInvoiceOverdue']);
        $events->listen(TicketCreated::class, [self::class, 'onTicketCreated']);
        $events->listen(TicketReplied::class, [self::class, 'onTicketReplied']);
        $events->listen(NodeWentOffline::class, [self::class, 'onNodeOffline']);
        $events->listen(NodeCameOnline::class, [self::class, 'onNodeOnline']);
    }

    public function onServiceCreated(ServiceCreated $event): void
    {
        $service = $event->service;

        $this->webhooks->dispatch(WebhookEvent::ServiceCreated, [
            'service_id' => $service->id,
            'client_id' => $service->client_id,
            'product_id' => $service->product_id,
            'status' => $service->status?->value ?? $service->status,
            'hostname' => $service->hostname,
        ]);
    }

    public function onServiceSuspended(ServiceSuspended $event): void
    {
        $service = $event->service;

        $this->webhooks->dispatch(WebhookEvent::ServiceSuspended, [
            'service_id' => $service->id,
            'client_id' => $service->client_id,
            'status' => $service->status?->value ?? $service->status,
        ]);
    }

    public function onServiceTerminated(ServiceTerminated $event): void
    {
        $service = $event->service;

        $this->webhooks->dispatch(WebhookEvent::ServiceTerminated, [
            'service_id' => $service->id,
            'client_id' => $service->client_id,
            'status' => $service->status?->value ?? $service->status,
        ]);
    }

    public function onInvoicePaid(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        $this->webhooks->dispatch(WebhookEvent::InvoicePaid, [
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $invoice->total_amount,
            'currency' => $invoice->currency,
        ]);
    }

    public function onInvoiceOverdue(InvoiceOverdue $event): void
    {
        $invoice = $event->invoice;

        $this->webhooks->dispatch(WebhookEvent::InvoiceOverdue, [
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $invoice->total_amount,
            'due_at' => $invoice->due_at?->toIso8601String(),
        ]);
    }

    public function onTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;

        $this->webhooks->dispatch(WebhookEvent::TicketCreated, [
            'ticket_id' => $ticket->id,
            'client_id' => $ticket->client_id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status?->value ?? $ticket->status,
            'priority' => $ticket->priority?->value ?? $ticket->priority,
        ]);
    }

    public function onTicketReplied(TicketReplied $event): void
    {
        $ticket = $event->ticket;
        $message = $event->message;

        $this->webhooks->dispatch(WebhookEvent::TicketReplied, [
            'ticket_id' => $ticket->id,
            'client_id' => $ticket->client_id,
            'ticket_number' => $ticket->ticket_number,
            'message_id' => $message->id,
            'author_id' => $event->author->id,
        ]);
    }

    public function onNodeOffline(NodeWentOffline $event): void
    {
        $node = $event->node;

        $this->webhooks->dispatch(WebhookEvent::NodeOffline, [
            'node_id' => $node->id,
            'name' => $node->name,
            'hostname' => $node->hostname,
            'status' => $node->status?->value ?? $node->status,
        ]);
    }

    public function onNodeOnline(NodeCameOnline $event): void
    {
        $node = $event->node;

        $this->webhooks->dispatch(WebhookEvent::NodeOnline, [
            'node_id' => $node->id,
            'name' => $node->name,
            'hostname' => $node->hostname,
            'status' => $node->status?->value ?? $node->status,
        ]);
    }
}
