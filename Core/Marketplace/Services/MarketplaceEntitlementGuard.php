<?php

namespace Core\Marketplace\Services;

use Core\License\Services\EntitlementService;
use Core\Marketplace\DataTransferObjects\MarketplaceInstallDecision;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\Enums\MarketplaceEntitlementReason;
use Core\Marketplace\Enums\MarketplaceInstallAction;
use Core\Marketplace\Exceptions\MarketplaceEntitlementException;

/**
 * Enforces license entitlements before marketplace package installation.
 */
class MarketplaceEntitlementGuard
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly MarketplaceClient $marketplace,
    ) {
    }

    public function evaluate(MarketplaceProduct $product): MarketplaceInstallDecision
    {
        if (! $this->enforcementEnabled()) {
            return $this->decision(
                product: $product,
                allowed: true,
                action: MarketplaceInstallAction::Install,
                reason: MarketplaceEntitlementReason::EnforcementDisabled,
            );
        }

        if (! in_array($product->productType, ['module', 'theme'], true)) {
            return $this->decision(
                product: $product,
                allowed: false,
                action: MarketplaceInstallAction::View,
                reason: MarketplaceEntitlementReason::UnsupportedProductType,
                message: 'Only module and theme marketplace packages can be installed.',
            );
        }

        if ($product->isFree() && $this->allowFreeWithoutEntitlement()) {
            return $this->decision(
                product: $product,
                allowed: true,
                action: MarketplaceInstallAction::Install,
                reason: MarketplaceEntitlementReason::AllowedFree,
            );
        }

        if (! $this->entitlements->isLicensed()) {
            return $this->decision(
                product: $product,
                allowed: false,
                action: $product->isFree() ? MarketplaceInstallAction::View : MarketplaceInstallAction::Purchase,
                reason: MarketplaceEntitlementReason::MissingLicense,
                message: 'Activate a valid license before installing marketplace packages.',
            );
        }

        if ($this->isEntitled($product)) {
            return $this->decision(
                product: $product,
                allowed: true,
                action: MarketplaceInstallAction::Install,
                reason: MarketplaceEntitlementReason::AllowedEntitled,
            );
        }

        return $this->decision(
            product: $product,
            allowed: false,
            action: $product->isFree() ? MarketplaceInstallAction::View : MarketplaceInstallAction::Purchase,
            reason: MarketplaceEntitlementReason::MissingEntitlement,
            message: 'Package ['.$product->slug.'] is not covered by the current license entitlements.',
        );
    }

    public function canInstall(MarketplaceProduct $product): bool
    {
        return $this->evaluate($product)->canInstall();
    }

    public function assertCanInstall(MarketplaceProduct $product): MarketplaceInstallDecision
    {
        $decision = $this->evaluate($product);

        if (! $decision->canInstall()) {
            throw MarketplaceEntitlementException::fromDecision($decision);
        }

        return $decision;
    }

    /**
     * Resolve a catalogue product by slug and assert install entitlement.
     */
    public function assertCanInstallSlug(string $productSlug): MarketplaceProduct
    {
        $product = $this->marketplace->getProduct($productSlug);
        $this->assertCanInstall($product);

        return $product;
    }

    public function evaluateSlug(string $productSlug): MarketplaceInstallDecision
    {
        return $this->evaluate($this->marketplace->getProduct($productSlug));
    }

    private function isEntitled(MarketplaceProduct $product): bool
    {
        $candidates = array_values(array_filter([
            $product->sku,
            $product->slug,
        ], static fn (string $value): bool => trim($value) !== ''));

        foreach ($candidates as $candidate) {
            if ($product->isModule() && $this->entitlements->hasModule($candidate)) {
                return true;
            }

            if ($product->isTheme() && $this->entitlements->hasTheme($candidate)) {
                return true;
            }

            if ($this->entitlements->has($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function decision(
        MarketplaceProduct $product,
        bool $allowed,
        MarketplaceInstallAction $action,
        MarketplaceEntitlementReason $reason,
        ?string $message = null,
    ): MarketplaceInstallDecision {
        return new MarketplaceInstallDecision(
            allowed: $allowed,
            action: $action,
            reason: $reason,
            productSku: $product->sku,
            productSlug: $product->slug,
            productType: $product->productType,
            isFree: $product->isFree(),
            message: $message,
        );
    }

    private function enforcementEnabled(): bool
    {
        return (bool) config('corepanel.marketplace.entitlements.enforce', true);
    }

    private function allowFreeWithoutEntitlement(): bool
    {
        return (bool) config('corepanel.marketplace.entitlements.allow_free_without_entitlement', true);
    }
}
