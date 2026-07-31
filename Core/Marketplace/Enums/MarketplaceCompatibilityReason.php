<?php

namespace Core\Marketplace\Enums;

enum MarketplaceCompatibilityReason: string
{
    case Compatible = 'compatible';
    case BelowMinimum = 'below_minimum';
    case AboveMaximum = 'above_maximum';
    case EnforcementDisabled = 'enforcement_disabled';
}
