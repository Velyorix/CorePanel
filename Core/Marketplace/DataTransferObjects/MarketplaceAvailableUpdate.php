<?php

namespace Core\Marketplace\DataTransferObjects;

final readonly class MarketplaceAvailableUpdate
{
    public function __construct(
        public string $productType,
        public string $packageKey,
        public string $marketplaceSlug,
        public string $installedVersion,
        public string $availableVersion,
        public bool $compatible,
        public ?string $sku = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_type' => $this->productType,
            'package_key' => $this->packageKey,
            'marketplace_slug' => $this->marketplaceSlug,
            'installed_version' => $this->installedVersion,
            'available_version' => $this->availableVersion,
            'compatible' => $this->compatible,
            'sku' => $this->sku,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            productType: (string) ($payload['product_type'] ?? ''),
            packageKey: (string) ($payload['package_key'] ?? ''),
            marketplaceSlug: (string) ($payload['marketplace_slug'] ?? ''),
            installedVersion: (string) ($payload['installed_version'] ?? ''),
            availableVersion: (string) ($payload['available_version'] ?? ''),
            compatible: (bool) ($payload['compatible'] ?? false),
            sku: isset($payload['sku']) && is_string($payload['sku']) ? $payload['sku'] : null,
        );
    }
}
