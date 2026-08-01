<?php

namespace Core\Automation\Support;

/**
 * Canonical automation event aliases (string triggers for workflows/rules).
 */
final class AutomationEvent
{
    public const SERVICE_CREATED = 'service.created';

    public const SERVICE_SUSPENDED = 'service.suspended';

    public const SERVICE_TERMINATED = 'service.terminated';

    public const INVOICE_PAID = 'invoice.paid';

    public const INVOICE_OVERDUE = 'invoice.overdue';

    public const TICKET_CREATED = 'ticket.created';

    public const TICKET_REPLIED = 'ticket.replied';

    public const NODE_OFFLINE = 'node.offline';

    public const NODE_ONLINE = 'node.online';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::SERVICE_CREATED,
            self::SERVICE_SUSPENDED,
            self::SERVICE_TERMINATED,
            self::INVOICE_PAID,
            self::INVOICE_OVERDUE,
            self::TICKET_CREATED,
            self::TICKET_REPLIED,
            self::NODE_OFFLINE,
            self::NODE_ONLINE,
        ];
    }

    public static function label(string $event): string
    {
        return match ($event) {
            self::SERVICE_CREATED => __('Service created'),
            self::SERVICE_SUSPENDED => __('Service suspended'),
            self::SERVICE_TERMINATED => __('Service terminated'),
            self::INVOICE_PAID => __('Invoice paid'),
            self::INVOICE_OVERDUE => __('Invoice overdue'),
            self::TICKET_CREATED => __('Ticket created'),
            self::TICKET_REPLIED => __('Ticket replied'),
            self::NODE_OFFLINE => __('Node offline'),
            self::NODE_ONLINE => __('Node online'),
            default => $event,
        };
    }
}
