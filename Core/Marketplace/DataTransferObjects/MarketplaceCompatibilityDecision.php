<?php

namespace Core\Marketplace\DataTransferObjects;

use Core\Marketplace\Enums\MarketplaceCompatibilityReason;

final readonly class MarketplaceCompatibilityDecision
{
    public function __construct(
        public bool $compatible,
        public MarketplaceCompatibilityReason $reason,
        public string $cmsVersion,
        public ?string $minCmsVersion,
        public ?string $maxCmsVersion,
        public ?string $packageVersion = null,
        public ?string $message = null,
    ) {
    }
}
