<?php

namespace Core\Billing\Services;

use Core\Billing\DataTransferObjects\DiscountResult;
use Core\Billing\Enums\CouponAppliesTo;
use Core\Billing\Exceptions\InvalidCouponException;
use Core\Billing\Models\Coupon;
use Core\Billing\Models\CouponRedemption;
use Core\Clients\Models\Client;
use Core\Orders\Models\Cart;
use Core\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Coupon lookup, validation, preview, and redemption.
 */
class CouponService
{
    public function __construct(
        private readonly DiscountCalculator $discountCalculator,
    ) {
    }

    public function findByCode(string $code): ?Coupon
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            return null;
        }

        return Coupon::query()->where('code', $normalized)->first();
    }

    public function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  list<int>|null  $productIds  Cart/product restriction check context
     */
    public function validateForOrder(
        Coupon $coupon,
        Client $client,
        ?Cart $cart = null,
        ?string $currency = null,
        ?array $productIds = null,
    ): void {
        if (! $coupon->active) {
            throw new InvalidCouponException(__('This coupon is inactive.'));
        }

        if ($coupon->applies_to !== CouponAppliesTo::Order) {
            throw new InvalidCouponException(__('This coupon cannot be applied to orders.'));
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            throw new InvalidCouponException(__('This coupon is not active yet.'));
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            throw new InvalidCouponException(__('This coupon has expired.'));
        }

        if ($coupon->max_uses !== null && (int) $coupon->uses_count >= (int) $coupon->max_uses) {
            throw new InvalidCouponException(__('This coupon has reached its usage limit.'));
        }

        if ($coupon->client_id !== null && (int) $coupon->client_id !== (int) $client->id) {
            throw new InvalidCouponException(__('This coupon is not valid for your account.'));
        }

        $perClient = max(0, (int) $coupon->max_uses_per_client);

        if ($perClient > 0) {
            $usedByClient = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('client_id', $client->id)
                ->count();

            if ($usedByClient >= $perClient) {
                throw new InvalidCouponException(__('You have already used this coupon.'));
            }
        }

        $resolvedCurrency = $currency
            ?? $cart?->currency
            ?? 'EUR';

        if (
            $coupon->type->value === 'fixed'
            && $coupon->currency !== null
            && strtoupper((string) $coupon->currency) !== strtoupper((string) $resolvedCurrency)
        ) {
            throw new InvalidCouponException(__('This coupon currency does not match your cart.'));
        }

        $allowedProducts = $coupon->product_ids;

        if (is_array($allowedProducts) && $allowedProducts !== []) {
            $allowed = array_map('intval', $allowedProducts);
            $cartProductIds = $productIds;

            if ($cartProductIds === null && $cart !== null) {
                $cart->loadMissing('items');
                $cartProductIds = $cart->items
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            }

            $cartProductIds = array_values(array_unique(array_map('intval', $cartProductIds ?? [])));

            if ($cartProductIds === [] || count(array_intersect($allowed, $cartProductIds)) === 0) {
                throw new InvalidCouponException(__('This coupon does not apply to the products in your cart.'));
            }
        }
    }

    public function previewForOrder(
        string $code,
        Client $client,
        Cart $cart,
        string $subtotal,
        ?string $currency = null,
    ): DiscountResult {
        $coupon = $this->findByCode($code);

        if ($coupon === null) {
            throw new InvalidCouponException(__('Invalid coupon code.'));
        }

        $this->validateForOrder($coupon, $client, $cart, $currency ?? $cart->currency);

        return $this->discountCalculator->calculate(
            $subtotal,
            $coupon,
            $currency ?? $cart->currency,
        );
    }

    public function redeem(
        Coupon $coupon,
        Client $client,
        Order $order,
        string $discountAmount,
    ): CouponRedemption {
        return DB::transaction(function () use ($coupon, $client, $order, $discountAmount): CouponRedemption {
            $existing = CouponRedemption::query()
                ->where('order_id', $order->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $locked = Coupon::query()
                ->whereKey($coupon->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateForOrder(
                $locked,
                $client,
                currency: $order->currency,
                productIds: $order->items()->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->all(),
            );

            $redemption = CouponRedemption::query()->create([
                'coupon_id' => $locked->id,
                'client_id' => $client->id,
                'order_id' => $order->id,
                'discount_amount' => number_format(round((float) $discountAmount, 2), 2, '.', ''),
                'redeemed_at' => now(),
            ]);

            $locked->forceFill([
                'uses_count' => (int) $locked->uses_count + 1,
            ])->save();

            return $redemption;
        });
    }
}
