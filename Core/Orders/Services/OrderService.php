<?php

namespace Core\Orders\Services;

use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Order lifecycle state machine (roadmap 11.2).
 * Cart → order conversion remains in OrderConversionService until 11.3.
 */
class OrderService
{
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
