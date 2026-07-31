<?php

namespace Core\Marketplace\DataTransferObjects;

final readonly class MarketplaceUpdateCheckResult
{
    /**
     * @param  list<MarketplaceAvailableUpdate>  $updates
     */
    public function __construct(
        public array $updates,
        public int $checkedCount,
        public string $checkedAt,
    ) {
    }

    public function hasUpdates(): bool
    {
        return $this->updates !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'checked_count' => $this->checkedCount,
            'checked_at' => $this->checkedAt,
            'updates' => array_map(
                static fn (MarketplaceAvailableUpdate $update): array => $update->toArray(),
                $this->updates,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $updates = [];

        foreach ($payload['updates'] ?? [] as $entry) {
            if (is_array($entry)) {
                $updates[] = MarketplaceAvailableUpdate::fromArray($entry);
            }
        }

        return new self(
            updates: $updates,
            checkedCount: (int) ($payload['checked_count'] ?? count($updates)),
            checkedAt: (string) ($payload['checked_at'] ?? now()->toIso8601String()),
        );
    }
}
