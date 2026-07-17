<?php

namespace Core\Orders\Services;

use Core\Clients\Models\Client;
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
 * Order lifecycle state machine + checkout conversion (roadmap 11.2 / 11.3).
 */
class OrderService
{
    public function __construct(
        private readonly CartSummary $cartSummary,
    ) {
    }

    /**
     * @param  array{
     *     currency?: string|null,
     *     payment_method?: string|null,
     *     coupon_code?: string|null,
     *     contact_name?: string|null,
     *     contact_email?: string|null,
     *     company_name?: string|null,
     *     vat_number?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     country?: string|null,
     *     postal_code?: string|null,
     *     phone?: string|null,
     *     notes?: string|null,
     *     created_by?: int|null,
     *     source?: string|OrderSource|null,
     *     cart_id?: int|null,
     *     subtotal_recurring?: string|float|null,
     *     subtotal_setup?: string|float|null,
     *     tax_amount?: string|float|null,
     *     total_amount?: string|float|null
     * }  $attributes
     */
    public function createDraft(Client $client, array $attributes = []): Order
    {
        $source = $attributes['source'] ?? OrderSource::Admin;

        if (is_string($source)) {
            $source = OrderSource::tryFrom($source) ?? throw new InvalidArgumentException(
                "Invalid order source [{$attributes['source']}].",
            );
        }

        if (! $source instanceof OrderSource) {
            throw new InvalidArgumentException('A valid order source is required.');
        }

        return Order::query()->create([
            'client_id' => $client->id,
            'created_by' => $attributes['created_by'] ?? null,
            'source' => $source,
            'cart_id' => $attributes['cart_id'] ?? null,
            'status' => OrderStatus::Draft,
            'currency' => $attributes['currency'] ?? 'EUR',
            'payment_method' => $attributes['payment_method'] ?? null,
            'coupon_code' => $attributes['coupon_code'] ?? null,
            'contact_name' => $attributes['contact_name'] ?? null,
            'contact_email' => $attributes['contact_email'] ?? null,
            'company_name' => $attributes['company_name'] ?? null,
            'vat_number' => $attributes['vat_number'] ?? null,
            'address' => $attributes['address'] ?? null,
            'city' => $attributes['city'] ?? null,
            'country' => isset($attributes['country'])
                ? strtoupper((string) $attributes['country'])
                : null,
            'postal_code' => $attributes['postal_code'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'subtotal_recurring' => $attributes['subtotal_recurring'] ?? '0.00',
            'subtotal_setup' => $attributes['subtotal_setup'] ?? '0.00',
            'tax_amount' => $attributes['tax_amount'] ?? '0.00',
            'total_amount' => $attributes['total_amount'] ?? '0.00',
            'placed_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
        ])->fresh(['items', 'client']) ?? throw new RuntimeException('Failed to create draft order.');
    }

    /**
     * Create a pending_payment order from an open client cart + checkout draft (roadmap 11.3).
     */
    public function createFromCheckout(Cart $cart, CheckoutDraftData $draft): Order
    {
        if ($cart->client_id === null) {
            throw new InvalidArgumentException(
                'A client account is required to convert a cart into an order.',
            );
        }

        if (! $cart->isOpen()) {
            throw new RuntimeException('Only open carts can be converted into orders.');
        }

        $cart->loadMissing(['items.product', 'client']);

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

        $client = $cart->client;

        if ($client === null) {
            throw new InvalidArgumentException(
                'A client account is required to convert a cart into an order.',
            );
        }

        $summary = $this->cartSummary->summarize($cart);

        return DB::transaction(function () use ($cart, $client, $draft, $summary): Order {
            $order = $this->createDraft($client, [
                'source' => OrderSource::ClientCheckout,
                'cart_id' => $cart->id,
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
            ]);

            foreach ($cart->items as $item) {
                $this->createOrderItem($order, $item);
            }

            $order = $this->markPendingPayment($order->fresh(['items', 'client']) ?? $order);

            $cart->forceFill([
                'status' => CartStatus::Converted,
            ])->save();

            return $order->fresh(['items.product', 'client']) ?? $order;
        });
    }

    /**
     * draft → pending_payment (roadmap "pending").
     */
    public function markPendingPayment(Order $order): Order
    {
        if ($order->status === OrderStatus::PendingPayment) {
            return $order->fresh(['items', 'client']) ?? $order;
        }

        if (! $order->status->canTransitionTo(OrderStatus::PendingPayment)) {
            throw new RuntimeException('Only draft orders can be marked pending payment.');
        }

        return $this->transition($order, OrderStatus::PendingPayment, [
            'placed_at' => $order->placed_at ?? now(),
            'order_number' => $order->order_number ?? $this->generateOrderNumber($order),
        ]);
    }

    /**
     * pending_payment → paid.
     */
    public function markPaid(Order $order): Order
    {
        if ($order->status === OrderStatus::Paid) {
            return $order->fresh(['items', 'client']) ?? $order;
        }

        if (! $order->status->canTransitionTo(OrderStatus::Paid)) {
            throw new RuntimeException('Only pending payment orders can be marked paid.');
        }

        return $this->transition($order, OrderStatus::Paid, [
            'paid_at' => $order->paid_at ?? now(),
        ]);
    }

    /**
     * draft | pending_payment → cancelled.
     */
    public function cancel(Order $order, ?string $reason = null): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            return $order->fresh(['items', 'client']) ?? $order;
        }

        if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
            throw new RuntimeException('This order cannot be cancelled from its current status.');
        }

        $extra = [
            'cancelled_at' => $order->cancelled_at ?? now(),
        ];

        $reason = is_string($reason) ? trim($reason) : null;

        if ($reason !== null && $reason !== '') {
            $extra['notes'] = filled($order->notes)
                ? rtrim((string) $order->notes)."\n".$reason
                : $reason;
        }

        return $this->transition($order, OrderStatus::Cancelled, $extra);
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(Order $order, OrderStatus $target, array $extra = []): Order
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new RuntimeException(sprintf(
                'Cannot transition order from %s to %s.',
                $order->status->value,
                $target->value,
            ));
        }

        return DB::transaction(function () use ($order, $target, $extra): Order {
            $order->forceFill([
                'status' => $target,
                ...$extra,
            ])->save();

            return $order->fresh(['items', 'client']) ?? $order;
        });
    }

    private function generateOrderNumber(Order $order): string
    {
        return sprintf(
            'ORD-%s-%06d',
            $order->created_at?->format('Ymd') ?? now()->format('Ymd'),
            $order->id,
        );
    }
}
