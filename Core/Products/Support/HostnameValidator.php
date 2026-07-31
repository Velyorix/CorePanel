<?php

namespace Core\Products\Support;

use InvalidArgumentException;

/**
 * FQDN / domain name validation for product configure.
 */
final class HostnameValidator
{
    /**
     * Validate a hostname or domain name (FQDN with at least two labels).
     *
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $value, string $fieldLabel = 'hostname'): string
    {
        $normalized = strtolower(rtrim(trim($value), '.'));

        if ($normalized === '') {
            throw new InvalidArgumentException("The {$fieldLabel} is required.");
        }

        if (strlen($normalized) > 253) {
            throw new InvalidArgumentException(
                "The {$fieldLabel} may not be greater than 253 characters.",
            );
        }

        if (
            ! preg_match(
                '/^(?=.{1,253}$)(?!-)[a-z0-9-]{1,63}(?<!-)(?:\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/',
                $normalized,
            )
        ) {
            throw new InvalidArgumentException(
                "The {$fieldLabel} must be a valid fully qualified domain name.",
            );
        }

        return $normalized;
    }

    public static function looksLikeHostnameFormat(?string $format, string $key): bool
    {
        if (in_array($format, ['hostname', 'domain', 'fqdn'], true)) {
            return true;
        }

        return in_array($key, ['hostname', 'domain'], true);
    }
}
