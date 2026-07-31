<?php

namespace Core\Products\Services;

use Core\Orders\DataTransferObjects\CartItemData;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductOptionType;
use Core\Products\Models\Product;
use Core\Products\Models\ProductAddon;
use Core\Products\Models\ProductPricing;
use InvalidArgumentException;

/**
 * First-payment and recurring amount helpers for catalog pricing.
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
     * @param  list<ProductAddon|string>  $addons
     */
    public function firstPaymentForProduct(
        Product $product,
        BillingCycle $cycle,
        array $addons = [],
    ): string {
        $breakdown = $this->configuredBreakdown($product, $cycle, null, $addons, 1);

        return $breakdown['first_payment_subtotal'];
    }

    /**
     * Recurring total for a product cycle + selected addons (no setup fees).
     *
     * @param  list<ProductAddon|string>  $addons
     */
    public function recurringTotalForProduct(
        Product $product,
        BillingCycle $cycle,
        array $addons = [],
    ): string {
        $breakdown = $this->configuredBreakdown($product, $cycle, null, $addons, 1);

        return $breakdown['recurring_subtotal'];
    }

    /**
     * Sum of option price deltas for the selected configuration.
     *
     * @param  array<string, mixed>|null  $options
     */
    public function optionDeltas(Product $product, ?array $options): string
    {
        $product->loadMissing('options');

        $total = 0.0;

        foreach ($options ?? [] as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $option = $product->optionByKey($key);

            if ($option === null) {
                continue;
            }

            if ($option->type === ProductOptionType::Select) {
                foreach ($option->choices() as $choice) {
                    if ((string) ($choice['value'] ?? '') === (string) $value) {
                        $total += (float) ($choice['price_delta'] ?? 0);
                        break;
                    }
                }

                continue;
            }

            if (
                $option->type === ProductOptionType::Checkbox
                && filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ) {
                $total += (float) (($option->config['price_delta'] ?? 0));
            }
        }

        return $this->money($total);
    }

    /**
     * Full configured pricing breakdown (HT, before tax preview).
     *
     * @param  array<string, mixed>|null  $options
     * @param  list<ProductAddon|string>  $addons
     * @return array{
     *     base_price: string,
     *     option_deltas: string,
     *     addons_recurring: string,
     *     unit_price: string,
     *     product_setup_fee: string,
     *     addons_setup_fee: string,
     *     setup_fee: string,
     *     quantity: int,
     *     recurring_subtotal: string,
     *     first_payment_subtotal: string
     * }
     */
    public function configuredBreakdown(
        Product $product,
        BillingCycle $cycle,
        ?array $options = null,
        array $addons = [],
        int $quantity = 1,
    ): array {
        $pricing = $product->pricingFor($cycle);

        if ($pricing === null || ! $pricing->is_enabled) {
            throw new InvalidArgumentException(
                "No enabled pricing found for billing cycle [{$cycle->value}].",
            );
        }

        $quantity = max(1, $quantity);
        $resolvedAddons = $this->resolveAddons($product, $addons);

        $basePrice = $this->recurringAmount($pricing);
        $optionDeltas = $this->optionDeltas($product, $options);
        $addonsRecurring = '0.00';
        $addonsSetup = '0.00';

        foreach ($resolvedAddons as $addon) {
            $addonsRecurring = $this->add($addonsRecurring, $addon->price);
            $addonsSetup = $this->add($addonsSetup, $addon->setup_fee);
        }

        $unitPrice = $this->add($this->add($basePrice, $optionDeltas), $addonsRecurring);
        $productSetup = $this->setupFee($pricing);
        $setupFee = $this->add($productSetup, $addonsSetup);
        $recurringSubtotal = $this->money((float) $unitPrice * $quantity);
        $firstPayment = $this->add($recurringSubtotal, $setupFee);

        return [
            'base_price' => $basePrice,
            'option_deltas' => $optionDeltas,
            'addons_recurring' => $addonsRecurring,
            'unit_price' => $unitPrice,
            'product_setup_fee' => $productSetup,
            'addons_setup_fee' => $addonsSetup,
            'setup_fee' => $setupFee,
            'quantity' => $quantity,
            'recurring_subtotal' => $recurringSubtotal,
            'first_payment_subtotal' => $firstPayment,
        ];
    }

    /**
     * @return array{
     *     base_price: string,
     *     option_deltas: string,
     *     addons_recurring: string,
     *     unit_price: string,
     *     product_setup_fee: string,
     *     addons_setup_fee: string,
     *     setup_fee: string,
     *     quantity: int,
     *     recurring_subtotal: string,
     *     first_payment_subtotal: string
     * }
     */
    public function configuredBreakdownFromCartItem(Product $product, CartItemData $item): array
    {
        return $this->configuredBreakdown(
            $product,
            $item->billingCycle,
            $item->options,
            $item->addons ?? [],
            $item->quantity,
        );
    }

    /**
     * @param  list<ProductAddon|string>  $addons
     * @return list<ProductAddon>
     */
    private function resolveAddons(Product $product, array $addons): array
    {
        $product->loadMissing('addons');
        $resolved = [];

        foreach ($addons as $addon) {
            if ($addon instanceof ProductAddon) {
                if (! $addon->is_enabled) {
                    throw new InvalidArgumentException("Addon [{$addon->key}] is disabled.");
                }

                $resolved[] = $addon;

                continue;
            }

            $key = trim((string) $addon);
            $found = $product->addonByKey($key);

            if ($found === null || ! $found->is_enabled) {
                throw new InvalidArgumentException("Addon [{$key}] is not available on this product.");
            }

            $resolved[] = $found;
        }

        return $resolved;
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
