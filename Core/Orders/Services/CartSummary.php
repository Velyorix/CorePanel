<?php

namespace Core\Orders\Services;

use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\Exceptions\InvalidCouponException;
use Core\Billing\Services\CouponService;
use Core\Billing\Services\DiscountCalculator;
use Core\Billing\Services\TaxCalculationService;
use Core\Clients\Models\Client;
use Core\Orders\Models\Cart;

/**
 * Aggregates snapshotted cart line totals with tax and optional coupon discount.
 */
class CartSummary
{
    public function __construct(
        private readonly TaxCalculationService $taxCalculation,
        private readonly CouponService $couponService,
        private readonly DiscountCalculator $discountCalculator,
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
     *     discount_amount: string,
     *     discounted_subtotal: string,
     *     coupon_code: string|null,
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
    public function summarize(Cart $cart, ?TaxAddress $address = null, ?string $couponCode = null): array
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

        $discountAmount = '0.00';
        $discountedSubtotal = $this->money($firstPayment);
        $resolvedCouponCode = null;

        if ($couponCode !== null && $cart->client instanceof Client) {
            try {
                $preview = $this->couponService->previewForOrder(
                    $couponCode,
                    $cart->client,
                    $cart,
                    $this->money($firstPayment),
                    $cart->currency,
                );
                $discountAmount = $preview->discountAmount;
                $discountedSubtotal = $preview->discountedSubtotal;
                $resolvedCouponCode = $this->couponService->normalizeCode($couponCode);
            } catch (InvalidCouponException) {
                // Soft-fail for previews; OrderService hard-validates on place.
            }
        }

        $recurringTax = $this->taxCalculation->calculateLine($this->money($recurring), $resolvedAddress);
        $firstTax = $this->taxCalculation->calculateLine($discountedSubtotal, $resolvedAddress);

        return [
            'currency' => $cart->currency,
            'item_count' => $itemCount,
            'lines' => $lines,
            'recurring_subtotal' => $this->money($recurring),
            'setup_subtotal' => $this->money($setup),
            'first_payment_subtotal' => $this->money($firstPayment),
            'discount_amount' => $discountAmount,
            'discounted_subtotal' => $discountedSubtotal,
            'coupon_code' => $resolvedCouponCode,
            'tax_rate' => $firstTax->taxRate,
            'tax_label' => $firstTax->taxLabel,
            'tax_is_estimate' => $firstTax->application === 'fallback',
            'tax_engine' => $firstTax->application === 'fallback' ? 'stub' : 'tax_rules',
            'recurring_tax' => $recurringTax->taxAmount,
            'first_payment_tax' => $firstTax->taxAmount,
            'recurring_total' => $this->money($recurring + (float) $recurringTax->taxAmount),
            'first_payment_total' => $this->money((float) $discountedSubtotal + (float) $firstTax->taxAmount),
        ];
    }

    /**
     * Summarize pre-priced lines (admin order builder).
     *
     * @param  list<array{unit_price: string|float, setup_fee: string|float, quantity: int, product_id?: int|null}>  $pricedLines
     * @return array{
     *     recurring_subtotal: string,
     *     setup_subtotal: string,
     *     first_payment_subtotal: string,
     *     discount_amount: string,
     *     discounted_subtotal: string,
     *     coupon_code: string|null,
     *     first_payment_tax: string,
     *     first_payment_total: string
     * }
     */
    public function summarizePricedLines(
        array $pricedLines,
        ?TaxAddress $address = null,
        ?Client $client = null,
        ?string $couponCode = null,
        ?string $currency = 'EUR',
    ): array {
        $recurring = 0.0;
        $setup = 0.0;
        $productIds = [];

        foreach ($pricedLines as $line) {
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $recurring += (float) ($line['unit_price'] ?? 0) * $qty;
            $setup += (float) ($line['setup_fee'] ?? 0);

            if (isset($line['product_id'])) {
                $productIds[] = (int) $line['product_id'];
            }
        }

        $firstPayment = $recurring + $setup;
        $resolvedAddress = $address ?? new TaxAddress(null);
        $discountAmount = '0.00';
        $discountedSubtotal = $this->money($firstPayment);
        $resolvedCouponCode = null;

        if ($couponCode !== null && $client !== null) {
            try {
                $coupon = $this->couponService->findByCode($couponCode);

                if ($coupon === null) {
                    throw new InvalidCouponException(__('Invalid coupon code.'));
                }

                $this->couponService->validateForOrder(
                    $coupon,
                    $client,
                    currency: $currency,
                    productIds: $productIds,
                );

                $preview = $this->discountCalculator->calculate(
                    $this->money($firstPayment),
                    $coupon,
                    $currency,
                );
                $discountAmount = $preview->discountAmount;
                $discountedSubtotal = $preview->discountedSubtotal;
                $resolvedCouponCode = $coupon->code;
            } catch (InvalidCouponException) {
                // Soft-fail for admin preview.
            }
        }

        $firstTax = $this->taxCalculation->calculateLine($discountedSubtotal, $resolvedAddress);

        return [
            'recurring_subtotal' => $this->money($recurring),
            'setup_subtotal' => $this->money($setup),
            'first_payment_subtotal' => $this->money($firstPayment),
            'discount_amount' => $discountAmount,
            'discounted_subtotal' => $discountedSubtotal,
            'coupon_code' => $resolvedCouponCode,
            'first_payment_tax' => $firstTax->taxAmount,
            'first_payment_total' => $this->money((float) $discountedSubtotal + (float) $firstTax->taxAmount),
        ];
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
