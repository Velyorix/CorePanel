<?php

namespace Core\Nodes\Exceptions;

use InvalidArgumentException;

class NodeSecurityException extends InvalidArgumentException
{
    public static function tlsRequired(string $url): self
    {
        return new self("Node API URL must use HTTPS: [{$url}]");
    }

    public static function ipNotWhitelisted(string $ip): self
    {
        return new self("Node target IP [{$ip}] is not allowed by the infrastructure IP whitelist.");
    }

    public static function ipWhitelistUnresolved(): self
    {
        return new self('Unable to resolve a target IP address for whitelist validation.');
    }

    public static function ipWhitelistEmpty(): self
    {
        return new self('IP whitelist is enabled but no allowed entries are configured.');
    }
}
