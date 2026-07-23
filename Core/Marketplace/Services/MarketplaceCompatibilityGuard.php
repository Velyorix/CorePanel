<?php

namespace Core\Marketplace\Services;

use Core\Marketplace\DataTransferObjects\MarketplaceCompatibilityDecision;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\DataTransferObjects\MarketplaceProductVersion;
use Core\Marketplace\Enums\MarketplaceCompatibilityReason;
use Core\Marketplace\Exceptions\MarketplaceCompatibilityException;

/**
 * Checks marketplace package compatibility against the local CorePanel version.
 */
class MarketplaceCompatibilityGuard
{
    public function evaluate(
        MarketplaceProductVersion $version,
        ?MarketplaceProduct $product = null,
        ?string $cmsVersion = null,
    ): MarketplaceCompatibilityDecision {
        $cmsVersion = $this->normalizeCmsVersion($cmsVersion ?? (string) config('corepanel.version', '0.0.0'));
        [$min, $max] = $this->constraintsFor($version, $product);

        if (! $this->enforcementEnabled()) {
            return new MarketplaceCompatibilityDecision(
                compatible: true,
                reason: MarketplaceCompatibilityReason::EnforcementDisabled,
                cmsVersion: $cmsVersion,
                minCmsVersion: $min,
                maxCmsVersion: $max,
                packageVersion: $version->version,
            );
        }

        if ($min !== null && version_compare($cmsVersion, $this->normalizeVersion($min), '<')) {
            return new MarketplaceCompatibilityDecision(
                compatible: false,
                reason: MarketplaceCompatibilityReason::BelowMinimum,
                cmsVersion: $cmsVersion,
                minCmsVersion: $min,
                maxCmsVersion: $max,
                packageVersion: $version->version,
                message: "Package requires CorePanel {$min} or newer (running {$cmsVersion}).",
            );
        }

        if ($max !== null && version_compare($cmsVersion, $this->normalizeVersion($max), '>')) {
            return new MarketplaceCompatibilityDecision(
                compatible: false,
                reason: MarketplaceCompatibilityReason::AboveMaximum,
                cmsVersion: $cmsVersion,
                minCmsVersion: $min,
                maxCmsVersion: $max,
                packageVersion: $version->version,
                message: "Package supports CorePanel up to {$max} (running {$cmsVersion}).",
            );
        }

        return new MarketplaceCompatibilityDecision(
            compatible: true,
            reason: MarketplaceCompatibilityReason::Compatible,
            cmsVersion: $cmsVersion,
            minCmsVersion: $min,
            maxCmsVersion: $max,
            packageVersion: $version->version,
        );
    }

    public function isCompatible(
        MarketplaceProductVersion $version,
        ?MarketplaceProduct $product = null,
        ?string $cmsVersion = null,
    ): bool {
        return $this->evaluate($version, $product, $cmsVersion)->compatible;
    }

    public function assertCompatible(
        MarketplaceProductVersion $version,
        ?MarketplaceProduct $product = null,
        ?string $cmsVersion = null,
    ): MarketplaceCompatibilityDecision {
        $decision = $this->evaluate($version, $product, $cmsVersion);

        if (! $decision->compatible) {
            throw MarketplaceCompatibilityException::fromDecision($decision);
        }

        return $decision;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function constraintsFor(
        MarketplaceProductVersion $version,
        ?MarketplaceProduct $product,
    ): array {
        $min = $version->minCmsVersion();
        $max = $version->maxCmsVersion();

        if ($min === null && $product !== null) {
            $value = $product->compatibility['min_version'] ?? null;
            $min = is_string($value) && $value !== '' ? $value : null;
        }

        if ($max === null && $product !== null) {
            $value = $product->compatibility['max_version'] ?? null;
            $max = is_string($value) && $value !== '' ? $value : null;
        }

        return [$min, $max];
    }

    private function normalizeCmsVersion(string $version): string
    {
        $version = trim($version);

        if ($version === '' || str_contains(strtolower($version), 'dev')) {
            return '0.0.0';
        }

        return $this->normalizeVersion($version);
    }

    private function normalizeVersion(string $version): string
    {
        $version = ltrim(trim($version), 'vV');

        if (preg_match('/^\d+\.\d+\.\d+/', $version, $matches) === 1) {
            return $matches[0];
        }

        return $version;
    }

    private function enforcementEnabled(): bool
    {
        return (bool) config('corepanel.marketplace.compatibility.enforce', true);
    }
}
