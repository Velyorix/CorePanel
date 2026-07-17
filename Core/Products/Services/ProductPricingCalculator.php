<?php

namespace Core\Products\Services;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductPricing;
use InvalidArgumentException;

/**
 * First-payment and recurring amount helpers for catalog pricing (roadmap 9.10).
 */
class ProductPricingCalculator
{
    /**
     * Recurring amount for an enabled pricing tier.
     */
    public function recurringAmount(ProductPricing $pricing): string
    {
        $this->assertEnabled($pricing);

        return $this->money($pricing->price);
    }

    /**
     * Setup fee for a pricing tier (may be zero).
     */
    public function setupFee(ProductPricing $pricing): string
    {
        $this->assertEnabled($pricing);

        return $this->money($pricing->setup_fee);
    }

    /**
     * First invoice total = recurring price + setup fee.
     */
    public function firstPaymentTotal(ProductPricing $pricing): string
    {
        $this->assertEnabled($pricing);

        return $this->add($pricing->price, $pricing->setup_fee);
    }

    /**
     * First payment for a product cycle, optionally including selected addons.
     *
     * @param  list<ProductAddon>  $addons
     */
    public function firstPaymentForProduct(
        Product $product,
        BillingCycle $cycle,
        array $addons = [],
    ): string {
        $pricing = $product->pricingFor($cycle);

        if ($pricing === null || ! $pricing->is_enabled) {
            throw new InvalidArgumentException(
                "No enabled pricing found for billing cycle [{$cycle->value}].",
            );
        }

        $total = $this->firstPaymentTotal($pricing);

        foreach ($addons as $addon) {
            if (! $addon->is_enabled) {
                throw new InvalidArgumentException("Addon [{$addon->key}] is disabled.");
            }

            $total = $this->add($total, $addon->price);
            $total = $this->add($total, $addon->setup_fee);
        }

        return $total;
    }

    /**
     * Recurring total for a product cycle + selected addons (no setup fees).
     *
     * @param  list<ProductAddon>  $addons
     */
    public function recurringTotalForProduct(
        Product $product,
        BillingCycle $cycle,
        array $addons = [],
    ): string {
        $pricing = $product->pricingFor($cycle);

        if ($pricing === null || ! $pricing->is_enabled) {
            throw new InvalidArgumentException(
                "No enabled pricing found for billing cycle [{$cycle->value}].",
            );
        }

        $total = $this->recurringAmount($pricing);

        foreach ($addons as $addon) {
            if (! $addon->is_enabled) {
                throw new InvalidArgumentException("Addon [{$addon->key}] is disabled.");
            }

            $total = $this->add($total, $addon->price);
        }

        return $total;
    }

    private function assertEnabled(ProductPricing $pricing): void
    {
        if (! $pricing->is_enabled) {
            throw new InvalidArgumentException(
                "Pricing for cycle [{$pricing->billing_cycle->value}] is disabled.",
            );
        }
    }

    private function add(string|float $left, string|float $right): string
    {
        return $this->money((float) $left + (float) $right);
    }

    private function money(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
