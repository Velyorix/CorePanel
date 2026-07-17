<?php

namespace Core\Products\Services;

use Core\Orders\DataTransferObjects\CartItemData;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductOptionType;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Core\Products\Models\ProductOption;
use InvalidArgumentException;

/**
 * Builds validated cart line payloads from the client product configurator (roadmap 10.4).
 */
class ConfiguratorService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function buildCartItem(Product $product, array $input): CartItemData
    {
        if ($product->status !== ProductStatus::Published) {
            throw new InvalidArgumentException('Only published products can be configured.');
        }

        $product->loadMissing(['options', 'addons', 'pricing']);

        $cycleValue = (string) ($input['billing_cycle'] ?? '');
        $cycle = BillingCycle::tryFrom($cycleValue);

        if ($cycle === null) {
            throw new InvalidArgumentException('A valid billing cycle is required.');
        }

        $pricing = $product->pricingFor($cycle);

        if ($pricing === null || ! $pricing->is_enabled) {
            throw new InvalidArgumentException(
                "No enabled pricing found for billing cycle [{$cycle->value}].",
            );
        }

        $customIntervalDays = $input['custom_interval_days'] ?? null;
        if ($customIntervalDays !== null && $customIntervalDays !== '') {
            $customIntervalDays = (int) $customIntervalDays;
        } else {
            $customIntervalDays = null;
        }

        if ($cycle->requiresCustomInterval()) {
            $customIntervalDays ??= $pricing->custom_interval_days;

            if ($customIntervalDays === null || $customIntervalDays < 1) {
                throw new InvalidArgumentException(
                    'Custom billing cycles require custom_interval_days.',
                );
            }

            if (
                $pricing->custom_interval_days !== null
                && $customIntervalDays !== $pricing->custom_interval_days
            ) {
                throw new InvalidArgumentException(
                    'custom_interval_days does not match the product pricing configuration.',
                );
            }
        } elseif ($customIntervalDays !== null) {
            throw new InvalidArgumentException(
                'custom_interval_days is only allowed for custom billing cycles.',
            );
        }

        $options = $this->normalizeOptions($product, $input['options'] ?? []);
        $addons = $this->normalizeAddons($product, $input['addons'] ?? []);

        return CartItemData::fromArray([
            'product_id' => $product->id,
            'billing_cycle' => $cycle->value,
            'quantity' => max(1, (int) ($input['quantity'] ?? 1)),
            'custom_interval_days' => $customIntervalDays,
            'options' => $options,
            'addons' => $addons,
            'config_data' => null,
        ]);
    }

    /**
     * @param  mixed  $rawOptions
     * @return array<string, mixed>|null
     */
    private function normalizeOptions(Product $product, mixed $rawOptions): ?array
    {
        if ($rawOptions === null) {
            $rawOptions = [];
        }

        if (! is_array($rawOptions)) {
            throw new InvalidArgumentException('Options must be an array.');
        }

        $normalized = [];
        $knownKeys = [];

        foreach ($product->options as $option) {
            $knownKeys[$option->key] = true;
            $rawValue = $rawOptions[$option->key] ?? null;
            $value = $this->normalizeOptionValue($option, $rawValue);

            if ($option->required && $this->isEmptyOptionValue($option, $value)) {
                throw new InvalidArgumentException("The option [{$option->key}] is required.");
            }

            if (! $this->isEmptyOptionValue($option, $value)) {
                $normalized[$option->key] = $value;
            }
        }

        foreach (array_keys($rawOptions) as $key) {
            if (! is_string($key) || ! isset($knownKeys[$key])) {
                throw new InvalidArgumentException("Unknown option [{$key}].");
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeOptionValue(ProductOption $option, mixed $rawValue): mixed
    {
        return match ($option->type) {
            ProductOptionType::Text => $this->normalizeText($option, $rawValue),
            ProductOptionType::Select => $this->normalizeSelect($option, $rawValue),
            ProductOptionType::Checkbox => $this->normalizeCheckbox($rawValue),
            ProductOptionType::Quantity, ProductOptionType::Number => $this->normalizeNumeric($option, $rawValue),
        };
    }

    private function normalizeText(ProductOption $option, mixed $rawValue): ?string
    {
        if ($rawValue === null) {
            return null;
        }

        $value = trim((string) $rawValue);

        if ($value === '') {
            return null;
        }

        $maxLength = $option->config['max_length'] ?? null;

        if ($maxLength !== null && mb_strlen($value) > (int) $maxLength) {
            throw new InvalidArgumentException(
                "The option [{$option->key}] may not be greater than {$maxLength} characters.",
            );
        }

        return $value;
    }

    private function normalizeSelect(ProductOption $option, mixed $rawValue): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $value = (string) $rawValue;
        $allowed = array_map(
            static fn (array $choice): string => (string) $choice['value'],
            $option->choices(),
        );

        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(
                "Invalid value for option [{$option->key}].",
            );
        }

        return $value;
    }

    private function normalizeCheckbox(mixed $rawValue): bool
    {
        return filter_var($rawValue, FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizeNumeric(ProductOption $option, mixed $rawValue): int|float|null
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        if (! is_numeric($rawValue)) {
            throw new InvalidArgumentException(
                "The option [{$option->key}] must be numeric.",
            );
        }

        $value = $option->type === ProductOptionType::Quantity
            ? (int) $rawValue
            : (float) $rawValue;

        $min = $option->config['min'] ?? null;
        $max = $option->config['max'] ?? null;

        if ($min !== null && $value < (float) $min) {
            throw new InvalidArgumentException(
                "The option [{$option->key}] must be at least {$min}.",
            );
        }

        if ($max !== null && $value > (float) $max) {
            throw new InvalidArgumentException(
                "The option [{$option->key}] may not be greater than {$max}.",
            );
        }

        return $value;
    }

    private function isEmptyOptionValue(ProductOption $option, mixed $value): bool
    {
        if ($option->type === ProductOptionType::Checkbox) {
            return $value !== true;
        }

        return $value === null || $value === '';
    }

    /**
     * @param  mixed  $rawAddons
     * @return list<string>|null
     */
    private function normalizeAddons(Product $product, mixed $rawAddons): ?array
    {
        if ($rawAddons === null || $rawAddons === []) {
            return null;
        }

        if (! is_array($rawAddons)) {
            throw new InvalidArgumentException('Addons must be an array of addon keys.');
        }

        $normalized = [];

        foreach ($rawAddons as $key) {
            if (! is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('Each addon must be a non-empty string key.');
            }

            $key = trim($key);
            $addon = $product->addonByKey($key);

            if ($addon === null || ! $addon->is_enabled) {
                throw new InvalidArgumentException("Addon [{$key}] is not available on this product.");
            }

            $normalized[] = $key;
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? null : $normalized;
    }
}
