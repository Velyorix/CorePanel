<?php

namespace Core\Marketplace\DataTransferObjects;

final readonly class MarketplaceProductVersion
{
    /**
     * @param  array{min_version?: string|null, max_version?: string|null}  $compatibility
     * @param  list<mixed>  $dependencies
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $version,
        public bool $isLatest,
        public ?string $changelog,
        public array $compatibility,
        public array $dependencies,
        public bool $hasArchive,
        public ?int $archiveSizeBytes,
        public ?string $publishedAt,
        public array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $version = trim((string) ($payload['version'] ?? ''));

        if ($version === '') {
            throw new \InvalidArgumentException('Marketplace version payload is missing version.');
        }

        return new self(
            id: (string) ($payload['id'] ?? ''),
            version: $version,
            isLatest: (bool) ($payload['is_latest'] ?? false),
            changelog: isset($payload['changelog']) ? (string) $payload['changelog'] : null,
            compatibility: is_array($payload['compatibility'] ?? null) ? $payload['compatibility'] : [],
            dependencies: is_array($payload['dependencies'] ?? null) ? array_values($payload['dependencies']) : [],
            hasArchive: (bool) ($payload['has_archive'] ?? false),
            archiveSizeBytes: isset($payload['archive_size_bytes']) && is_numeric($payload['archive_size_bytes'])
                ? (int) $payload['archive_size_bytes']
                : null,
            publishedAt: isset($payload['published_at']) ? (string) $payload['published_at'] : null,
            raw: $payload,
        );
    }

    public function minCmsVersion(): ?string
    {
        $value = $this->compatibility['min_version'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function maxCmsVersion(): ?string
    {
        $value = $this->compatibility['max_version'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
