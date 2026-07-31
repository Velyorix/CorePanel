<?php

namespace Core\Marketplace\Enums;

/**
 * Outcome reason for a marketplace install entitlement check.
 */
enum MarketplaceEntitlementReason: string
{
    case AllowedFree = 'allowed_free';
    case AllowedEntitled = 'allowed_entitled';
    case MissingLicense = 'missing_license';
    case MissingEntitlement = 'missing_entitlement';
    case UnsupportedProductType = 'unsupported_product_type';
    case EnforcementDisabled = 'enforcement_disabled';
}
