<?php

namespace Core\License\DataTransferObjects;

class LicenseEntitlement
{
    public function __construct(
        public readonly string $productType,
        public readonly string $productSku,
        public readonly string $productName,
        public readonly ?string $grantedAt,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $productSku = trim((string) ($payload['product_sku'] ?? ''));

        if ($productSku === '') {
            return null;
        }

        return new self(
            productType: strtolower(trim((string) ($payload['product_type'] ?? ''))),
            productSku: $productSku,
            productName: trim((string) ($payload['product_name'] ?? $productSku)),
            grantedAt: isset($payload['granted_at']) ? (string) $payload['granted_at'] : null,
        );
    }
}
