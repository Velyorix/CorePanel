<?php

namespace Core\API\Http\Presenters\V1;

use Core\Billing\Models\Invoice;
use Core\Clients\Models\Client;
use Core\Nodes\Models\Node;
use Core\Services\Models\Service;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;

final class ApiResourcePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function client(Client $client): array
    {
        return [
            'id' => $client->id,
            'company_name' => $client->company_name,
            'status' => $client->status?->value ?? $client->status,
            'country' => $client->country,
            'city' => $client->city,
            'vat_number' => $client->vat_number,
            'credit_balance' => $client->credit_balance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function service(Service $service): array
    {
        return [
            'id' => $service->id,
            'client_id' => $service->client_id,
            'product_id' => $service->product_id,
            'product_name' => $service->product?->name,
            'status' => $service->status?->value ?? $service->status,
            'hostname' => $service->hostname,
            'ip_address' => $service->ip_address,
            'billing_cycle' => $service->billing_cycle?->value ?? $service->billing_cycle,
            'renewal_date' => $service->renewal_date?->toIso8601String(),
            'next_billing_date' => $service->next_billing_date?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invoice(Invoice $invoice): array
    {
        $payload = [
            'id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status?->value ?? $invoice->status,
            'currency' => $invoice->currency,
            'total_amount' => $invoice->total_amount,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];

        if ($invoice->relationLoaded('items')) {
            $payload['items'] = $invoice->items->map(static fn ($item): array => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->line_total,
            ])->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ticket(Ticket $ticket): array
    {
        $payload = [
            'id' => $ticket->id,
            'client_id' => $ticket->client_id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status?->value ?? $ticket->status,
            'priority' => $ticket->priority?->value ?? $ticket->priority,
            'category_id' => $ticket->category_id,
            'service_id' => $ticket->service_id,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($ticket->relationLoaded('messages')) {
            $payload['messages'] = $ticket->messages
                ->map(static fn (TicketMessage $message): array => self::ticketMessage($message))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ticketMessage(TicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'author_name' => $message->author?->name,
            'message' => $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function node(Node $node): array
    {
        return [
            'id' => $node->id,
            'name' => $node->name,
            'type' => $node->type?->value ?? $node->type,
            'module' => $node->module,
            'hostname' => $node->hostname,
            'ip_address' => $node->ip_address,
            'status' => $node->status?->value ?? $node->status,
            'max_services' => $node->max_services,
            'node_group_id' => $node->node_group_id,
        ];
    }
}
