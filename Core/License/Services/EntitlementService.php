<?php

namespace Core\License\Services;

use Core\License\DataTransferObjects\LicenseEntitlement;
use Core\License\Models\LicenseActivation;

class EntitlementService
{
    public function __construct(
        private readonly LicenseSettings $licenseSettings,
    ) {
    }

    /**
     * @return list<LicenseEntitlement>
     */
    public function all(): array
    {
        return $this->entitlements();
    }

    /**
     * @return list<LicenseEntitlement>
     */
    public function modules(): array
    {
        return array_values(array_filter(
            $this->entitlements(),
            fn (LicenseEntitlement $entitlement): bool => $entitlement->productType === 'module',
        ));
    }

    /**
     * @return list<LicenseEntitlement>
     */
    public function themes(): array
    {
        return array_values(array_filter(
            $this->entitlements(),
            fn (LicenseEntitlement $entitlement): bool => $entitlement->productType === 'theme',
        ));
    }

    public function has(string $productSku): bool
    {
        $normalizedSku = $this->normalizeSku($productSku);

        foreach ($this->entitlements() as $entitlement) {
            if ($this->normalizeSku($entitlement->productSku) === $normalizedSku) {
                return true;
            }
        }

        return false;
    }

    public function hasModule(string $productSku): bool
    {
        $normalizedSku = $this->normalizeSku($productSku);

        foreach ($this->modules() as $entitlement) {
            if ($this->normalizeSku($entitlement->productSku) === $normalizedSku) {
                return true;
            }
        }

        return false;
    }

    public function hasTheme(string $productSku): bool
    {
        $normalizedSku = $this->normalizeSku($productSku);

        foreach ($this->themes() as $entitlement) {
            if ($this->normalizeSku($entitlement->productSku) === $normalizedSku) {
                return true;
            }
        }

        return false;
    }

    public function isLicensed(): bool
    {
        return $this->activation() !== null;
    }

    /**
     * @return list<LicenseEntitlement>
     */
    private function entitlements(): array
    {
        $activation = $this->activation();

        if ($activation === null || ! is_array($activation->entitlements)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $payload): ?LicenseEntitlement => LicenseEntitlement::fromArray($payload),
            $activation->entitlements,
        )));
    }

    private function activation(): ?LicenseActivation
    {
        $instanceId = $this->licenseSettings->instanceId();

        if (blank($instanceId)) {
            return null;
        }

        return LicenseActivation::query()
            ->where('instance_id', $instanceId)
            ->where('status', 'active')
            ->first();
    }

    private function normalizeSku(string $productSku): string
    {
        return strtolower(trim($productSku));
    }
}
