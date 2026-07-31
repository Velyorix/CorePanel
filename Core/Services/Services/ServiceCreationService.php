<?php

namespace Core\Services\Services;

use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Events\ServiceCreated;
use Core\Services\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceCreationService
{
    /**
     * Create one pending service per order line for a paid order.
     *
     * Idempotent: existing services linked by order_item_id are skipped.
     *
     * @return Collection<int, Service> Newly created services only.
     */
    public function createFromPaidOrder(Order $order): Collection
    {
        $order = $order->fresh(['items.product']) ?? $order;

        if ($order->status !== OrderStatus::Paid) {
            throw new RuntimeException('Services can only be created from paid orders.');
        }

        return DB::transaction(function () use ($order): Collection {
            $created = collect();

            foreach ($order->items as $item) {
                if (Service::query()->where('order_item_id', $item->id)->exists()) {
                    continue;
                }

                $created->push($this->createPendingFromOrderItem($order, $item));
            }

            return $created;
        });
    }

    private function createPendingFromOrderItem(Order $order, OrderItem $item): Service
    {
        $product = $item->product;

        $service = new Service([
            'client_id' => $order->client_id,
            'product_id' => $item->product_id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'status' => ServiceStatus::Pending,
            'module' => $product?->module,
            'billing_cycle' => $item->billing_cycle,
            'custom_interval_days' => $item->custom_interval_days,
            'config_data' => $this->snapshotConfigData($item),
            'hostname' => $this->resolveHostname($item),
        ]);

        $service->save();

        $service = $service->fresh(['client', 'product', 'order', 'orderItem']) ?? $service;

        event(new ServiceCreated($service));

        return $service;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotConfigData(OrderItem $item): ?array
    {
        $snapshot = array_filter([
            'options' => $item->options,
            'addons' => $item->addons,
            'config' => $item->config_data,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $snapshot === [] ? null : $snapshot;
    }

    private function resolveHostname(OrderItem $item): ?string
    {
        $product = $item->product;
        $key = $product?->hostnameOptionKey();

        if ($key === null) {
            return null;
        }

        $value = $item->options[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
