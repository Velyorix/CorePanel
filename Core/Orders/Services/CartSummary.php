<?php

namespace Core\Orders\Services;

use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\Services\TaxCalculationService;
use Core\Orders\Models\Cart;

/**
 * Aggregates snapshotted cart line totals with tax calculation.
 */
class CartSummary
{
    public function __construct(
        private readonly TaxCalculationService $taxCalculation,
    ) {
    }

    /**
     * @return array{
     *     currency: string|null,
     *     item_count: int,
     *     lines: list<array{
     *         id: int,
     *         product_name: string,
     *         billing_cycle: string,
     *         quantity: int,
     *         unit_price: string,
     *         setup_fee: string,
     *         line_subtotal: string,
     *         options: array<string, mixed>|null,
     *         addons: list<string>|null
     *     }>,
     *     recurring_subtotal: string,
     *     setup_subtotal: string,
     *     first_payment_subtotal: string,
     *     tax_rate: string,
     *     tax_label: string,
     *     tax_is_estimate: bool,
     *     tax_engine: string,
     *     first_payment_tax: string,
     *     first_payment_total: string,
     *     recurring_tax: string,
     *     recurring_total: string
     * }
     */
    public function summarize(Cart $cart, ?TaxAddress $address = null): array
    {
        $cart->loadMissing(['items.product', 'client']);

        $recurring = 0.0;
        $setup = 0.0;
        $lines = [];
        $itemCount = 0;

        foreach ($cart->items as $item) {
            $unit = (float) ($item->unit_price ?? 0);
            $lineSetup = (float) ($item->setup_fee ?? 0);
            $qty = max(1, (int) $item->quantity);

            $recurring += $unit * $qty;
            $setup += $lineSetup;
            $itemCount += $qty;

            $lines[] = [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? __('Unknown product'),
                'billing_cycle' => $item->billing_cycle->label(),
                'quantity' => $qty,
                'unit_price' => $this->money($unit),
                'setup_fee' => $this->money($lineSetup),
                'line_subtotal' => $item->lineSubtotal(),
                'options' => $item->options,
                'addons' => $item->addons,
            ];
        }

        $firstPayment = $recurring + $setup;
        $resolvedAddress = $address ?? ($cart->client !== null
            ? TaxAddress::fromClient($cart->client)
            : new TaxAddress(null));

        $recurringTax = $this->taxCalculation->calculateLine($this->money($recurring), $resolvedAddress);
        $firstTax = $this->taxCalculation->calculateLine($this->money($firstPayment), $resolvedAddress);

        return [
            'currency' => $cart->currency,
            'item_count' => $itemCount,
            'lines' => $lines,
            'recurring_subtotal' => $this->money($recurring),
            'setup_subtotal' => $this->money($setup),
            'first_payment_subtotal' => $this->money($firstPayment),
            'tax_rate' => $firstTax->taxRate,
            'tax_label' => $firstTax->taxLabel,
            'tax_is_estimate' => $firstTax->application === 'fallback',
            'tax_engine' => $firstTax->application === 'fallback' ? 'stub' : 'tax_rules',
            'recurring_tax' => $recurringTax->taxAmount,
            'first_payment_tax' => $firstTax->taxAmount,
            'recurring_total' => $this->money($recurring + (float) $recurringTax->taxAmount),
            'first_payment_total' => $this->money($firstPayment + (float) $firstTax->taxAmount),
        ];
    }

    /**
     * Summarize pre-priced lines (admin order builder).
     *
     * @param  list<array{unit_price: string|float, setup_fee: string|float, quantity: int}>  $pricedLines
     * @return array{
     *     recurring_subtotal: string,
     *     setup_subtotal: string,
     *     first_payment_subtotal: string,
     *     first_payment_tax: string,
     *     first_payment_total: string
     * }
     */
    public function summarizePricedLines(array $pricedLines, ?TaxAddress $address = null): array
    {
        $recurring = 0.0;
        $setup = 0.0;

        foreach ($pricedLines as $line) {
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $recurring += (float) ($line['unit_price'] ?? 0) * $qty;
            $setup += (float) ($line['setup_fee'] ?? 0);
        }

        $firstPayment = $recurring + $setup;
        $resolvedAddress = $address ?? new TaxAddress(null);
        $firstTax = $this->taxCalculation->calculateLine($this->money($firstPayment), $resolvedAddress);

        return [
            'recurring_subtotal' => $this->money($recurring),
            'setup_subtotal' => $this->money($setup),
            'first_payment_subtotal' => $this->money($firstPayment),
            'first_payment_tax' => $firstTax->taxAmount,
            'first_payment_total' => $this->money($firstPayment + (float) $firstTax->taxAmount),
        ];
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
