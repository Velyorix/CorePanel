<?php

namespace Core\Marketplace\DataTransferObjects;

final readonly class MarketplaceInstallResult
{
    public function __construct(
        public string $productSlug,
        public string $productType,
        public string $version,
        public string $packageKey,
        public string $installedPath,
        public bool $enabled,
    ) {
    }
}
