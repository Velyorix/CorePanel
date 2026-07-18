<?php

namespace Core\Orders\Services;

use Core\Auth\Models\User;
use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Clients\Models\Client;
use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Events\OrderCancelled;
use Core\Orders\Events\OrderCreated;
use Core\Orders\Events\OrderPaid;
use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Services\ProductPricingCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Order lifecycle state machine and checkout conversion.
 */
class OrderService
{
    public function __construct(
        private readonly CartSummary $cartSummary,
        private readonly ProductPricingCalculator $pricingCalculator,
    ) {
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: OrderStatus|null,
     *     source?: OrderSource|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $source = $filters['source'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'order_number',
            'status',
            'source',
            'total_amount',
            'placed_at',
            'created_at',
        ], true)) {
            $sort = 'created_at';
        }

        $query = Order::query()->with(['client', 'items']);

        if ($status instanceof OrderStatus) {
            $query->where('status', $status->value);
        }

        if ($source instanceof OrderSource) {
            $query->where('source', $source->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('order_number', 'like', $term)
                    ->orWhere('contact_name', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhereHas('client', function ($clientQuery) use ($term): void {
                        $clientQuery
                            ->where('company_name', 'like', $term)
                            ->orWhereHas('owner', function ($ownerQuery) use ($term): void {
                                $ownerQuery
                                    ->where('name', 'like', $term)
                                    ->orWhere('email', 'like', $term);
                            });
                    });

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Client order history — excludes drafts.
     *
     * @param  array{
     *     status?: OrderStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForClient(Client $client, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'placed_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['order_number', 'status', 'total_amount', 'placed_at', 'created_at'], true)) {
            $sort = 'placed_at';
        }

        $query = Order::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', OrderStatus::Draft->value)
            ->with(['items']);

        if ($status instanceof OrderStatus) {
            if ($status === OrderStatus::Draft) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('status', $status->value);
            }
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
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
     * Create a pending_payment order from an open client cart and checkout draft.
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

        $summary = $this->cartSummary->summarize(
            $cart,
            TaxAddress::fromDraft($draft),
        );

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
     * Create an order on behalf of a client without touching their open cart.
     *
     * @param  list<CartItemData>  $items
     */
    public function createFromAdmin(
        Client $client,
        User $creator,
        CheckoutDraftData $draft,
        array $items,
        bool $submitAsPending = false,
        ?string $notes = null,
        ?string $currency = null,
    ): Order {
        if ($items === []) {
            throw new InvalidArgumentException('At least one line item is required.');
        }

        if ($draft->paymentMethod === null || $draft->paymentMethod === '') {
            throw new InvalidArgumentException('A payment method is required.');
        }

        if ($draft->contactName === null || $draft->contactEmail === null) {
            throw new InvalidArgumentException('Contact name and email are required.');
        }

        if (
            $draft->address === null
            || $draft->city === null
            || $draft->country === null
            || $draft->postalCode === null
        ) {
            throw new InvalidArgumentException('A complete billing address is required.');
        }

        return DB::transaction(function () use (
            $client,
            $creator,
            $draft,
            $items,
            $submitAsPending,
            $notes,
            $currency,
        ): Order {
            $pricedLines = [];

            foreach ($items as $itemData) {
                if (! $itemData instanceof CartItemData) {
                    throw new InvalidArgumentException('Each item must be a CartItemData instance.');
                }

                $pricedLines[] = $this->priceCartItemData($itemData);
            }

            $summary = $this->cartSummary->summarizePricedLines(
                array_map(
                    static fn (array $line): array => [
                        'unit_price' => $line['unit_price'],
                        'setup_fee' => $line['setup_fee'],
                        'quantity' => $line['data']->quantity,
                    ],
                    $pricedLines,
                ),
                TaxAddress::fromDraft($draft),
            );

            $order = $this->createDraft($client, [
                'source' => OrderSource::Admin,
                'created_by' => $creator->id,
                'currency' => $currency ?? 'EUR',
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
                'notes' => $notes,
                'subtotal_recurring' => $summary['recurring_subtotal'],
                'subtotal_setup' => $summary['setup_subtotal'],
                'tax_amount' => $summary['first_payment_tax'],
                'total_amount' => $summary['first_payment_total'],
            ]);

            foreach ($pricedLines as $line) {
                $this->createOrderItemFromCartItemData(
                    $order,
                    $line['product'],
                    $line['data'],
                    $line['unit_price'],
                    $line['setup_fee'],
                    $line['line_total'],
                );
            }

            $order = $order->fresh(['items', 'client']) ?? $order;

            if ($submitAsPending) {
                $order = $this->markPendingPayment($order);
            }

            return $order->fresh(['items.product', 'client', 'creator']) ?? $order;
        });
    }

    /**
     * Transition draft → pending_payment (placed).
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
     * @return array{
     *     product: Product,
     *     data: CartItemData,
     *     unit_price: string,
     *     setup_fee: string,
     *     line_total: string
     * }
     */
    private function priceCartItemData(CartItemData $data): array
    {
        $product = Product::query()->with(['pricing', 'addons', 'options'])->find($data->productId);

        if ($product === null) {
            throw new InvalidArgumentException('The selected product does not exist.');
        }

        if ($product->status !== ProductStatus::Published) {
            throw new InvalidArgumentException('Only published products can be ordered.');
        }

        $pricing = $product->pricingFor($data->billingCycle);

        if ($pricing === null || ! $pricing->is_enabled) {
            throw new InvalidArgumentException(
                "No enabled pricing found for billing cycle [{$data->billingCycle->value}].",
            );
        }

        $breakdown = $this->pricingCalculator->configuredBreakdownFromCartItem($product, $data);

        return [
            'product' => $product,
            'data' => $data,
            'unit_price' => $breakdown['unit_price'],
            'setup_fee' => $breakdown['setup_fee'],
            'line_total' => number_format(
                ((float) $breakdown['unit_price'] * max(1, $data->quantity))
                + (float) $breakdown['setup_fee'],
                2,
                '.',
                '',
            ),
        ];
    }

    private function createOrderItemFromCartItemData(
        Order $order,
        Product $product,
        CartItemData $data,
        string $unitPrice,
        string $setupFee,
        string $lineTotal,
    ): OrderItem {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'billing_cycle' => $data->billingCycle,
            'custom_interval_days' => $data->customIntervalDays
                ?? $product->pricingFor($data->billingCycle)?->custom_interval_days,
            'quantity' => $data->quantity,
            'options' => $data->options,
            'addons' => $data->addons,
            'config_data' => $data->configData,
            'unit_price' => $unitPrice,
            'setup_fee' => $setupFee,
            'line_total' => $lineTotal,
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

            $fresh = $order->fresh(['items', 'client']) ?? $order;

            match ($target) {
                OrderStatus::PendingPayment => event(new OrderCreated($fresh)),
                OrderStatus::Paid => event(new OrderPaid($fresh)),
                OrderStatus::Cancelled => event(new OrderCancelled($fresh)),
                default => null,
            };

            return $fresh;
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
