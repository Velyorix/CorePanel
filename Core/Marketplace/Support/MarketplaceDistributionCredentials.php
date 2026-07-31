<?php

namespace Core\Marketplace\Support;

/**
 * Built-in credentials for verifying official marketplace package descriptors.
 */
final class MarketplaceDistributionCredentials
{
    /**
     * HMAC secret used to verify download descriptor signatures from corepanel.org.
     */
    public const SIGNATURE_SECRET = 'cpms_7Kp2mNqR9vXwL4hJ6fT8yB3cD5eG1aZ0sU';
}
