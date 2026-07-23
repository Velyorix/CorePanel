<?php

namespace Core\Marketplace\DataTransferObjects;

use Core\Marketplace\Enums\MarketplaceEntitlementReason;
use Core\Marketplace\Enums\MarketplaceInstallAction;

final readonly class MarketplaceInstallDecision
{
    public function __construct(
        public bool $allowed,
        public MarketplaceInstallAction $action,
        public MarketplaceEntitlementReason $reason,
        public string $productSku,
        public string $productSlug,
        public string $productType,
        public bool $isFree,
        public ?string $message = null,
    ) {
    }

    public function canInstall(): bool
    {
        return $this->allowed && $this->action === MarketplaceInstallAction::Install;
    }
}
