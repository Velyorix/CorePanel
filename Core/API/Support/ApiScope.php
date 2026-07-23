<?php

namespace Core\API\Support;

/**
 * Canonical API token scopes for the public REST API.
 */
final class ApiScope
{
    public const ALL = '*';

    public const ME = 'api.me';

    public const CLIENT_READ = 'api.client.read';

    public const SERVICE_READ = 'api.service.read';

    public const SERVICE_WRITE = 'api.service.write';

    public const INVOICE_READ = 'api.invoice.read';

    public const INVOICE_WRITE = 'api.invoice.write';

    public const TICKET_READ = 'api.ticket.read';

    public const TICKET_WRITE = 'api.ticket.write';

    public const NODE_READ = 'api.node.read';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ALL,
            self::ME,
            self::CLIENT_READ,
            'api.client.*',
            self::SERVICE_READ,
            self::SERVICE_WRITE,
            'api.service.*',
            self::INVOICE_READ,
            self::INVOICE_WRITE,
            'api.invoice.*',
            self::TICKET_READ,
            self::TICKET_WRITE,
            'api.ticket.*',
            self::NODE_READ,
            'api.node.*',
        ];
    }
}
