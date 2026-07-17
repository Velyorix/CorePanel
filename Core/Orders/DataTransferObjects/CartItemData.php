<?php

namespace Core\Orders\DataTransferObjects;

use Core\Products\Enums\BillingCycle;
use InvalidArgumentException;

readonly class CartItemData
{
    /**
     * @param  array<string, mixed>|null  $options
     * @param  list<string>|null  $addons
     * @param  array<string, mixed>|null  $configData
     */
    public function __construct(
        public int $productId,
        public BillingCycle $billingCycle,
        public int $quantity = 1,
        public ?int $customIntervalDays = null,
        public ?array $options = null,
        public ?array $addons = null,
        public ?array $configData = null,
    ) {
    }

    /**
     * @param  array{
     *     product_id?: int|null,
     *     billing_cycle?: string|null,
     *     quantity?: int|null,
     *     custom_interval_days?: int|null,
     *     options?: array<string, mixed>|null,
     *     addons?: list<string>|null,
     *     config_data?: array<string, mixed>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $productId = (int) ($data['product_id'] ?? 0);

        if ($productId < 1) {
            throw new InvalidArgumentException('The product_id is required.');
        }

        $cycleValue = (string) ($data['billing_cycle'] ?? '');
        $cycle = BillingCycle::tryFrom($cycleValue);

        if ($cycle === null) {
            throw new InvalidArgumentException("Invalid billing cycle [{$cycleValue}].");
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        $customIntervalDays = $data['custom_interval_days'] ?? null;
        if ($customIntervalDays !== null && $customIntervalDays !== '') {
            $customIntervalDays = (int) $customIntervalDays;
        } else {
            $customIntervalDays = null;
        }

        if ($cycle->requiresCustomInterval()) {
            if ($customIntervalDays === null || $customIntervalDays < 1) {
                throw new InvalidArgumentException(
                    'Custom billing cycles require custom_interval_days.',
                );
            }
        } elseif ($customIntervalDays !== null) {
            throw new InvalidArgumentException(
                'custom_interval_days is only allowed for custom billing cycles.',
            );
        }

        $addons = $data['addons'] ?? null;

        if ($addons !== null) {
            if (! is_array($addons)) {
                throw new InvalidArgumentException('Addons must be an array of addon keys.');
            }

            $normalizedAddons = [];

            foreach ($addons as $addon) {
                if (! is_string($addon) || trim($addon) === '') {
                    throw new InvalidArgumentException('Each addon must be a non-empty string key.');
                }

                $normalizedAddons[] = trim($addon);
            }

            $addons = array_values(array_unique($normalizedAddons));
        }

        $options = $data['options'] ?? null;
        if ($options !== null && ! is_array($options)) {
            throw new InvalidArgumentException('Options must be an array.');
        }

        $configData = $data['config_data'] ?? null;
        if ($configData !== null && ! is_array($configData)) {
            throw new InvalidArgumentException('Config data must be an array.');
        }

        return new self(
            productId: $productId,
            billingCycle: $cycle,
            quantity: $quantity,
            customIntervalDays: $customIntervalDays,
            options: $options === [] ? null : $options,
            addons: $addons === [] || $addons === null ? null : $addons,
            configData: $configData === [] ? null : $configData,
        );
    }
}
