<?php

namespace Core\Webhooks\Enums;

enum WebhookEvent: string
{
    case ServiceCreated = 'service.created';
    case ServiceSuspended = 'service.suspended';
    case ServiceTerminated = 'service.terminated';
    case InvoicePaid = 'invoice.paid';
    case InvoiceOverdue = 'invoice.overdue';
    case TicketCreated = 'ticket.created';
    case TicketReplied = 'ticket.replied';
    case NodeOffline = 'node.offline';
    case NodeOnline = 'node.online';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
