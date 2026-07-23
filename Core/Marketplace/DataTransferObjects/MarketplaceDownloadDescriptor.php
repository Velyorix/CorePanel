<?php

namespace Core\Marketplace\DataTransferObjects;

final readonly class MarketplaceDownloadDescriptor
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $downloadUrl,
        public ?string $filename,
        public ?string $checksumSha256,
        public ?string $signatureHmacSha256,
        public ?int $sizeBytes,
        public ?string $expiresAt,
        public array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $url = trim((string) ($payload['download_url'] ?? ''));

        if ($url === '') {
            throw new \InvalidArgumentException('Marketplace download payload is missing download_url.');
        }

        return new self(
            downloadUrl: $url,
            filename: isset($payload['filename']) ? (string) $payload['filename'] : null,
            checksumSha256: isset($payload['checksum_sha256']) ? strtolower((string) $payload['checksum_sha256']) : null,
            signatureHmacSha256: isset($payload['signature_hmac_sha256'])
                ? strtolower((string) $payload['signature_hmac_sha256'])
                : null,
            sizeBytes: isset($payload['size_bytes']) && is_numeric($payload['size_bytes'])
                ? (int) $payload['size_bytes']
                : null,
            expiresAt: isset($payload['expires_at']) ? (string) $payload['expires_at'] : null,
            raw: $payload,
        );
    }
}
