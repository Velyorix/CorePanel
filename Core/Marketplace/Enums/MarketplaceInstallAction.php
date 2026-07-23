<?php

namespace Core\Marketplace\Enums;

/**
 * UI / install action recommended after entitlement evaluation.
 */
enum MarketplaceInstallAction: string
{
    case Install = 'install';
    case Purchase = 'purchase';
    case View = 'view';
}
