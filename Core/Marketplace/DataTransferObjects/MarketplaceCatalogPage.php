<?php

namespace Core\Marketplace\DataTransferObjects;

final readonly class MarketplaceCatalogPage
{
    /**
     * @param  list<MarketplaceProduct>  $items
     * @param  array{current_page: int, per_page: int, total: int, last_page: int}  $meta
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $items,
        public array $meta,
        public array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        $items = [];

        foreach ($data as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $items[] = MarketplaceProduct::fromArray($entry);
        }

        return new self(
            items: $items,
            meta: [
                'current_page' => (int) ($meta['current_page'] ?? 1),
                'per_page' => (int) ($meta['per_page'] ?? count($items)),
                'total' => (int) ($meta['total'] ?? count($items)),
                'last_page' => (int) ($meta['last_page'] ?? 1),
            ],
            raw: $payload,
        );
    }

    public function currentPage(): int
    {
        return $this->meta['current_page'];
    }

    public function total(): int
    {
        return $this->meta['total'];
    }

    public function lastPage(): int
    {
        return $this->meta['last_page'];
    }
}
