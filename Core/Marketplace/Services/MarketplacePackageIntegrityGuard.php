<?php

namespace Core\Marketplace\Services;

use Core\Marketplace\DataTransferObjects\MarketplaceDownloadDescriptor;
use Core\Marketplace\Exceptions\MarketplaceInstallException;
use Core\Marketplace\Support\MarketplaceDistributionCredentials;

/**
 * Validates marketplace download descriptors and archives before installation.
 */
class MarketplacePackageIntegrityGuard
{
    public function assertDescriptor(MarketplaceDownloadDescriptor $descriptor): void
    {
        if ($this->enforceChecksum() && ! $this->hasChecksum($descriptor)) {
            throw MarketplaceInstallException::checksumRequired();
        }

        if (! $this->verifySignature()) {
            return;
        }

        if (! $this->hasSignature($descriptor)) {
            if ($this->enforceSignature()) {
                throw MarketplaceInstallException::signatureRequired();
            }

            return;
        }

        if (! $this->hasChecksum($descriptor)) {
            throw MarketplaceInstallException::checksumRequired();
        }

        $expected = $this->signChecksum($descriptor->checksumSha256);

        if (! hash_equals($expected, $descriptor->signatureHmacSha256)) {
            throw MarketplaceInstallException::signatureMismatch();
        }
    }

    public function assertArchive(string $path, MarketplaceDownloadDescriptor $descriptor): void
    {
        if (! is_file($path)) {
            throw MarketplaceInstallException::invalidArchive($path, 'file does not exist.');
        }

        $size = filesize($path);

        if ($descriptor->sizeBytes !== null && $descriptor->sizeBytes > 0) {
            if ($size === false || $size !== $descriptor->sizeBytes) {
                throw MarketplaceInstallException::sizeMismatch(
                    $descriptor->sizeBytes,
                    $size === false ? 0 : $size,
                );
            }
        }

        if (! $this->hasChecksum($descriptor)) {
            if ($this->enforceChecksum()) {
                throw MarketplaceInstallException::checksumRequired();
            }

            return;
        }

        $actual = hash_file('sha256', $path);

        if (! is_string($actual) || ! hash_equals($descriptor->checksumSha256, strtolower($actual))) {
            throw MarketplaceInstallException::checksumMismatch(
                $descriptor->checksumSha256,
                is_string($actual) ? strtolower($actual) : 'unreadable',
            );
        }
    }

    public function signChecksum(string $checksum): string
    {
        $checksum = strtolower(trim($checksum));

        if ($checksum === '') {
            throw new \InvalidArgumentException('Marketplace checksum cannot be empty.');
        }

        return hash_hmac('sha256', $checksum, $this->signatureSecret());
    }

    private function hasChecksum(MarketplaceDownloadDescriptor $descriptor): bool
    {
        return is_string($descriptor->checksumSha256) && $descriptor->checksumSha256 !== '';
    }

    private function hasSignature(MarketplaceDownloadDescriptor $descriptor): bool
    {
        return is_string($descriptor->signatureHmacSha256) && $descriptor->signatureHmacSha256 !== '';
    }

    private function enforceChecksum(): bool
    {
        return (bool) config('corepanel.marketplace.integrity.enforce_checksum', true);
    }

    private function verifySignature(): bool
    {
        return (bool) config('corepanel.marketplace.integrity.verify_signature', true);
    }

    private function enforceSignature(): bool
    {
        return (bool) config('corepanel.marketplace.integrity.enforce_signature', true);
    }

    private function signatureSecret(): string
    {
        $secret = config('corepanel.marketplace.integrity.signature_secret');

        if (is_string($secret) && trim($secret) !== '') {
            return trim($secret);
        }

        return MarketplaceDistributionCredentials::SIGNATURE_SECRET;
    }
}
