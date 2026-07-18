<?php

namespace Core\Products\Services;

use Core\Orders\DataTransferObjects\CartItemData;
use Core\Products\Models\Product;

/**
 * Client configurator price preview with tax estimate stub.
 * Full multi-country VAT calculation is not implemented yet.
 */
class CatalogPricePreview
{
    public function __construct(
        private readonly ProductPricingCalculator $calculator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCartItem(Product $product, CartItemData $item): array
    {
        $breakdown = $this->calculator->configuredBreakdownFromCartItem($product, $item);
        $taxRate = $this->taxPreviewRate();
        $taxLabel = $this->taxPreviewLabel();

        $recurringTax = $this->money((float) $breakdown['recurring_subtotal'] * $taxRate);
        $firstTax = $this->money((float) $breakdown['first_payment_subtotal'] * $taxRate);

        return [
            'currency' => null,
            'quantity' => $breakdown['quantity'],
            'base_price' => $breakdown['base_price'],
            'option_deltas' => $breakdown['option_deltas'],
            'addons_recurring' => $breakdown['addons_recurring'],
            'unit_price' => $breakdown['unit_price'],
            'product_setup_fee' => $breakdown['product_setup_fee'],
            'addons_setup_fee' => $breakdown['addons_setup_fee'],
            'setup_fee' => $breakdown['setup_fee'],
            'recurring_subtotal' => $breakdown['recurring_subtotal'],
            'first_payment_subtotal' => $breakdown['first_payment_subtotal'],
            'tax_rate' => number_format($taxRate, 4, '.', ''),
            'tax_label' => $taxLabel,
            'tax_is_estimate' => true,
            'tax_engine' => 'stub',
            'recurring_tax' => $recurringTax,
            'first_payment_tax' => $firstTax,
            'recurring_total' => $this->money(
                (float) $breakdown['recurring_subtotal'] + (float) $recurringTax,
            ),
            'first_payment_total' => $this->money(
                (float) $breakdown['first_payment_subtotal'] + (float) $firstTax,
            ),
        ];
    }

    private function taxPreviewRate(): float
    {
        $rate = (float) config('corepanel.billing.tax_preview_rate', 0);

        return max(0, $rate);
    }

    private function taxPreviewLabel(): string
    {
        $label = config('corepanel.billing.tax_preview_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return (string) __('Tax (estimate)');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
