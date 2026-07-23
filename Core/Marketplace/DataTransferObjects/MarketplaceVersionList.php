<?php

namespace Core\Marketplace\DataTransferObjects;

final readonly class MarketplaceVersionList
{
    /**
     * @param  array{id?: string|null, slug?: string|null, name?: string|null}  $product
     * @param  list<MarketplaceProductVersion>  $versions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $product,
        public array $versions,
        public array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $versions = [];

        foreach ($data as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $versions[] = MarketplaceProductVersion::fromArray($entry);
        }

        return new self(
            product: $product,
            versions: $versions,
            raw: $payload,
        );
    }

    public function latest(): ?MarketplaceProductVersion
    {
        foreach ($this->versions as $version) {
            if ($version->isLatest) {
                return $version;
            }
        }

        return $this->versions[0] ?? null;
    }

    public function find(string $version): ?MarketplaceProductVersion
    {
        foreach ($this->versions as $entry) {
            if ($entry->version === $version) {
                return $entry;
            }
        }

        return null;
    }
}
