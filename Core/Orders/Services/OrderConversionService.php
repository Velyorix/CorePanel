<?php

namespace Core\Orders\Services;

use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Converts an open cart + checkout draft into a pending_payment order (roadmap 10.8 / 11.1).
 * Full OrderService state machine arrives in étape 11.2.
 */
class OrderConversionService
{
    public function __construct(
        private readonly CartSummary $cartSummary,
    ) {
    }

    public function convertFromCheckout(Cart $cart, CheckoutDraftData $draft): Order
    {
        if ($cart->client_id === null) {
            throw new InvalidArgumentException(
                'A client account is required to convert a cart into an order.',
            );
        }

        if (! $cart->isOpen()) {
            throw new RuntimeException('Only open carts can be converted into orders.');
        }

        $cart->loadMissing('items.product');

        if ($cart->items->isEmpty()) {
            throw new InvalidArgumentException('Cannot convert an empty cart into an order.');
        }

        if ($draft->paymentMethod === null || $draft->paymentMethod === '') {
            throw new InvalidArgumentException('A payment method is required to place the order.');
        }

        if ($draft->contactName === null || $draft->contactEmail === null) {
            throw new InvalidArgumentException('Contact name and email are required to place the order.');
        }

        if (
            $draft->address === null
            || $draft->city === null
            || $draft->country === null
            || $draft->postalCode === null
        ) {
            throw new InvalidArgumentException('A complete billing address is required to place the order.');
        }

        $existing = Order::query()->where('cart_id', $cart->id)->first();

        if ($existing !== null) {
            throw new RuntimeException('This cart has already been converted into an order.');
        }

        $summary = $this->cartSummary->summarize($cart);

        return DB::transaction(function () use ($cart, $draft, $summary): Order {
            $order = Order::query()->create([
                'client_id' => $cart->client_id,
                'cart_id' => $cart->id,
                'source' => OrderSource::ClientCheckout,
                'status' => OrderStatus::Draft,
                'currency' => $cart->currency,
                'payment_method' => $draft->paymentMethod,
                'coupon_code' => $draft->couponCode,
                'contact_name' => $draft->contactName,
                'contact_email' => $draft->contactEmail,
                'company_name' => $draft->companyName,
                'vat_number' => $draft->vatNumber,
                'address' => $draft->address,
                'city' => $draft->city,
                'country' => $draft->country,
                'postal_code' => $draft->postalCode,
                'phone' => $draft->phone,
                'subtotal_recurring' => $summary['recurring_subtotal'],
                'subtotal_setup' => $summary['setup_subtotal'],
                'tax_amount' => $summary['first_payment_tax'],
                'total_amount' => $summary['first_payment_total'],
                'placed_at' => null,
            ]);

            foreach ($cart->items as $item) {
                $this->createOrderItem($order, $item);
            }

            $order->forceFill([
                'order_number' => $this->generateOrderNumber($order),
                'status' => OrderStatus::PendingPayment,
                'placed_at' => now(),
            ])->save();

            $cart->forceFill([
                'status' => CartStatus::Converted,
            ])->save();

            return $order->fresh(['items.product', 'client']) ?? $order;
        });
    }

    private function createOrderItem(Order $order, CartItem $item): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product?->name,
            'product_slug' => $item->product?->slug,
            'billing_cycle' => $item->billing_cycle,
            'custom_interval_days' => $item->custom_interval_days,
            'quantity' => $item->quantity,
            'options' => $item->options,
            'addons' => $item->addons,
            'config_data' => $item->config_data,
            'unit_price' => $item->unit_price,
            'setup_fee' => $item->setup_fee,
            'line_total' => $item->lineSubtotal(),
        ]);
    }

    private function generateOrderNumber(Order $order): string
    {
        return sprintf('ORD-%s-%06d', $order->created_at?->format('Ymd') ?? now()->format('Ymd'), $order->id);
    }
}
