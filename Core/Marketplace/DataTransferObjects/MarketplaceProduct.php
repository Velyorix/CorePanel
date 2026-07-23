<?php

namespace Core\Marketplace\DataTransferObjects;

/**
 * Marketplace product summary / detail from corepanel.org.
 *
 * @phpstan-type CategoryArray array{id?: string|null, slug?: string|null, name?: string|null}
 * @phpstan-type DeveloperArray array{id?: string|null, name?: string|null}
 * @phpstan-type PricingArray array{is_free?: bool, amount?: int|null, currency?: string|null}
 * @phpstan-type CompatibilityArray array{min_version?: string|null, max_version?: string|null}
 * @phpstan-type RatingsArray array{average?: int|null, count?: int|null}
 * @phpstan-type StatsArray array{download_count?: int|null}
 */
final readonly class MarketplaceProduct
{
    /**
     * @param  CategoryArray  $category
     * @param  DeveloperArray  $developer
     * @param  PricingArray  $pricing
     * @param  CompatibilityArray  $compatibility
     * @param  RatingsArray  $ratings
     * @param  StatsArray  $stats
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $sku,
        public string $slug,
        public string $name,
        public ?string $shortDescription,
        public string $productType,
        public array $category,
        public array $developer,
        public array $pricing,
        public array $compatibility,
        public array $ratings,
        public array $stats,
        public ?string $currentVersion,
        public bool $isVip,
        public ?string $publishedAt,
        public ?string $thumbnailUrl,
        public array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $slug = trim((string) ($payload['slug'] ?? ''));

        if ($slug === '') {
            throw new \InvalidArgumentException('Marketplace product payload is missing slug.');
        }

        return new self(
            id: (string) ($payload['id'] ?? ''),
            sku: (string) ($payload['sku'] ?? ''),
            slug: $slug,
            name: (string) ($payload['name'] ?? $slug),
            shortDescription: isset($payload['short_description']) ? (string) $payload['short_description'] : null,
            productType: strtolower(trim((string) ($payload['product_type'] ?? ''))),
            category: is_array($payload['category'] ?? null) ? $payload['category'] : [],
            developer: is_array($payload['developer'] ?? null) ? $payload['developer'] : [],
            pricing: is_array($payload['pricing'] ?? null) ? $payload['pricing'] : [],
            compatibility: is_array($payload['compatibility'] ?? null) ? $payload['compatibility'] : [],
            ratings: is_array($payload['ratings'] ?? null) ? $payload['ratings'] : [],
            stats: is_array($payload['stats'] ?? null) ? $payload['stats'] : [],
            currentVersion: isset($payload['current_version']) ? (string) $payload['current_version'] : null,
            isVip: (bool) ($payload['is_vip'] ?? false),
            publishedAt: isset($payload['published_at']) ? (string) $payload['published_at'] : null,
            thumbnailUrl: isset($payload['thumbnail_url']) ? (string) $payload['thumbnail_url'] : null,
            raw: $payload,
        );
    }

    public function isFree(): bool
    {
        return (bool) ($this->pricing['is_free'] ?? false);
    }

    public function isModule(): bool
    {
        return $this->productType === 'module';
    }

    public function isTheme(): bool
    {
        return $this->productType === 'theme';
    }
}
