<?php

namespace Core\Orders\Services;

use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductPricing;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CartService
{
    /**
     * Resolve an open cart for a guest session and/or authenticated client.
     * Creates one when missing.
     */
    public function getOrCreate(?Client $client, ?string $sessionId, ?string $currency = null): Cart
    {
        $sessionId = $this->normalizeSessionId($sessionId);

        if ($client === null && $sessionId === null) {
            throw new InvalidArgumentException(
                'A client or session_id is required to resolve a cart.',
            );
        }

        if ($client !== null) {
            $cart = Cart::query()
                ->where('client_id', $client->id)
                ->where('status', CartStatus::Open->value)
                ->first();

            if ($cart !== null) {
                return $cart->load('items.product');
            }

            return Cart::query()->create([
                'client_id' => $client->id,
                'session_id' => null,
                'status' => CartStatus::Open,
                'currency' => $this->normalizeCurrency($currency),
            ])->load('items.product');
        }

        $cart = Cart::query()
            ->whereNull('client_id')
            ->where('session_id', $sessionId)
            ->where('status', CartStatus::Open->value)
            ->first();

        if ($cart !== null) {
            return $cart->load('items.product');
        }

        return Cart::query()->create([
            'client_id' => null,
            'session_id' => $sessionId,
            'status' => CartStatus::Open,
            'currency' => $this->normalizeCurrency($currency),
        ])->load('items.product');
    }

    public function resolve(?Client $client, ?string $sessionId, ?string $currency = null): Cart
    {
        return $this->getOrCreate($client, $sessionId, $currency);
    }

    public function addItem(Cart $cart, CartItemData $data): CartItem
    {
        $this->assertCartIsMutable($cart);

        $product = Product::query()->with(['pricing', 'addons'])->find($data->productId);

        if ($product === null) {
            throw new InvalidArgumentException('The selected product does not exist.');
        }

        if ($product->status !== ProductStatus::Published) {
            throw new InvalidArgumentException('Only published products can be added to the cart.');
        }

        $pricing = $this->resolveEnabledPricing($product, $data);
        $this->assertAddonsBelongToProduct($product, $data->addons);

        return DB::transaction(function () use ($cart, $product, $pricing, $data): CartItem {
            $cart->refresh();
            $this->assertCartIsMutable($cart);

            $candidate = new CartItem([
                'product_id' => $product->id,
                'billing_cycle' => $data->billingCycle,
                'custom_interval_days' => $data->customIntervalDays ?? $pricing->custom_interval_days,
                'quantity' => $data->quantity,
                'options' => $data->options,
                'addons' => $data->addons,
                'config_data' => $data->configData,
                'unit_price' => $this->money($pricing->price),
                'setup_fee' => $this->money($pricing->setup_fee),
            ]);

            $fingerprint = $candidate->configurationFingerprint();

            $existing = $cart->items()
                ->with('product')
                ->get()
                ->first(fn (CartItem $item): bool => $item->configurationFingerprint() === $fingerprint);

            if ($existing !== null) {
                $existing->forceFill([
                    'quantity' => $existing->quantity + $data->quantity,
                    'unit_price' => $candidate->unit_price,
                    'setup_fee' => $candidate->setup_fee,
                ])->save();

                return $existing->fresh(['product']) ?? $existing;
            }

            $candidate->cart_id = $cart->id;
            $candidate->save();

            return $candidate->fresh(['product']) ?? $candidate;
        });
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        $this->assertCartIsMutable($cart);
        $this->assertItemBelongsToCart($cart, $item);

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1. Use removeItem to delete a line.');
        }

        $item->forceFill(['quantity' => $quantity])->save();

        return $item->fresh(['product']) ?? $item;
    }

    public function removeItem(Cart $cart, CartItem $item): void
    {
        $this->assertCartIsMutable($cart);
        $this->assertItemBelongsToCart($cart, $item);

        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $this->assertCartIsMutable($cart);

        $cart->items()->delete();
    }

    /**
     * Merge a guest session cart into the client's open cart (login / ensure-client).
     */
    public function mergeSessionCartIntoClient(string $sessionId, Client $client): Cart
    {
        $sessionId = $this->normalizeSessionId($sessionId);

        if ($sessionId === null) {
            throw new InvalidArgumentException('A session_id is required to merge carts.');
        }

        return DB::transaction(function () use ($sessionId, $client): Cart {
            $clientCart = $this->getOrCreate($client, null);

            $sessionCart = Cart::query()
                ->whereNull('client_id')
                ->where('session_id', $sessionId)
                ->where('status', CartStatus::Open->value)
                ->with('items')
                ->first();

            if ($sessionCart === null || $sessionCart->id === $clientCart->id) {
                return $clientCart->load('items.product');
            }

            if ($sessionCart->items->isEmpty()) {
                $sessionCart->forceFill([
                    'status' => CartStatus::Abandoned,
                ])->save();

                return $clientCart->load('items.product');
            }

            $clientCart->load('items');

            foreach ($sessionCart->items as $sessionItem) {
                $fingerprint = $sessionItem->configurationFingerprint();

                $match = $clientCart->items->first(
                    fn (CartItem $item): bool => $item->configurationFingerprint() === $fingerprint,
                );

                if ($match !== null) {
                    $match->forceFill([
                        'quantity' => $match->quantity + $sessionItem->quantity,
                    ])->save();
                    $sessionItem->delete();

                    continue;
                }

                $sessionItem->forceFill([
                    'cart_id' => $clientCart->id,
                ])->save();
            }

            $sessionCart->forceFill([
                'status' => CartStatus::Abandoned,
            ])->save();

            return $clientCart->fresh(['items.product']) ?? $clientCart;
        });
    }

    private function resolveEnabledPricing(Product $product, CartItemData $data): ProductPricing
    {
        $pricing = $product->pricingFor($data->billingCycle);

        if ($pricing === null || ! $pricing->is_enabled) {
            throw new InvalidArgumentException(
                "No enabled pricing found for billing cycle [{$data->billingCycle->value}].",
            );
        }

        if ($data->billingCycle->requiresCustomInterval()) {
            $interval = $data->customIntervalDays ?? $pricing->custom_interval_days;

            if ($interval === null || $interval < 1) {
                throw new InvalidArgumentException(
                    'Custom billing cycles require custom_interval_days.',
                );
            }

            if (
                $pricing->custom_interval_days !== null
                && $data->customIntervalDays !== null
                && $pricing->custom_interval_days !== $data->customIntervalDays
            ) {
                throw new InvalidArgumentException(
                    'custom_interval_days does not match the product pricing configuration.',
                );
            }
        }

        return $pricing;
    }

    /**
     * @param  list<string>|null  $addonKeys
     */
    private function assertAddonsBelongToProduct(Product $product, ?array $addonKeys): void
    {
        if ($addonKeys === null || $addonKeys === []) {
            return;
        }

        foreach ($addonKeys as $key) {
            $addon = $product->addonByKey($key);

            if ($addon === null) {
                throw new InvalidArgumentException("Addon [{$key}] is not available on this product.");
            }

            if (! $addon->is_enabled) {
                throw new InvalidArgumentException("Addon [{$key}] is disabled.");
            }
        }
    }

    private function assertCartIsMutable(Cart $cart): void
    {
        if (! $cart->status->isMutable()) {
            throw new RuntimeException(
                "Cart [{$cart->id}] is {$cart->status->value} and cannot be modified.",
            );
        }
    }

    private function assertItemBelongsToCart(Cart $cart, CartItem $item): void
    {
        if ($item->cart_id !== $cart->id) {
            throw new InvalidArgumentException('The cart item does not belong to this cart.');
        }
    }

    private function normalizeSessionId(?string $sessionId): ?string
    {
        if ($sessionId === null) {
            return null;
        }

        $sessionId = trim($sessionId);

        return $sessionId === '' ? null : $sessionId;
    }

    private function normalizeCurrency(?string $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $currency = strtoupper(trim($currency));

        if ($currency === '') {
            return null;
        }

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }

        return $currency;
    }

    private function money(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
