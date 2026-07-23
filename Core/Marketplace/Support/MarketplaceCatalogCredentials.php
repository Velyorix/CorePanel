<?php

namespace Core\Marketplace\Support;

/**
 * Built-in credentials for the public marketplace catalogue API.
 *
 * The CMS ships with a read-only corepanel.org token (marketplace:read) so
 * operators never need to configure one. Optional env override remains for
 * staging / alternate org endpoints.
 */
final class MarketplaceCatalogCredentials
{
    /**
     * Platform catalogue token — scope marketplace:read only.
     */
    public const TOKEN = 'cpat_DeK4hsWheizpD3DaiBXxlGhjl8NMpImnxeNJqOYX';
}
