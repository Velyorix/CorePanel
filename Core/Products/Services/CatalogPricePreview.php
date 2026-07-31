<?php

namespace Core\Products\Services;

use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\Services\TaxCalculationService;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Products\Models\Product;

/**
 * Client configurator price preview with tax estimate.
 */
class CatalogPricePreview
{
    public function __construct(
        private readonly ProductPricingCalculator $calculator,
        private readonly TaxCalculationService $taxCalculation,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCartItem(Product $product, CartItemData $item, ?TaxAddress $address = null): array
    {
        $breakdown = $this->calculator->configuredBreakdownFromCartItem($product, $item);
        $resolvedAddress = $address ?? new TaxAddress(null);

        $recurringTax = $this->taxCalculation->calculateLine(
            (string) $breakdown['recurring_subtotal'],
            $resolvedAddress,
        );
        $firstTax = $this->taxCalculation->calculateLine(
            (string) $breakdown['first_payment_subtotal'],
            $resolvedAddress,
        );

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
            'tax_rate' => $firstTax->taxRate,
            'tax_label' => $firstTax->taxLabel,
            'tax_is_estimate' => $firstTax->application === 'fallback',
            'tax_engine' => $firstTax->application === 'fallback' ? 'stub' : 'tax_rules',
            'recurring_tax' => $recurringTax->taxAmount,
            'first_payment_tax' => $firstTax->taxAmount,
            'recurring_total' => $this->money(
                (float) $breakdown['recurring_subtotal'] + (float) $recurringTax->taxAmount,
            ),
            'first_payment_total' => $this->money(
                (float) $breakdown['first_payment_subtotal'] + (float) $firstTax->taxAmount,
            ),
        ];
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
