<?php

namespace Core\Products\DataTransferObjects;

use Core\Products\Enums\BillingCycle;
use InvalidArgumentException;

readonly class ProductPricingData
{
    public function __construct(
        public BillingCycle $billingCycle,
        public string $price,
        public string $setupFee = '0.00',
        public bool $isEnabled = true,
        public ?int $customIntervalDays = null,
    ) {
    }

    /**
     * @param  array{
     *     billing_cycle?: string,
     *     price?: mixed,
     *     setup_fee?: mixed,
     *     is_enabled?: mixed,
     *     custom_interval_days?: int|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $cycleValue = (string) ($data['billing_cycle'] ?? '');
        $cycle = BillingCycle::tryFrom($cycleValue);

        if ($cycle === null) {
            throw new InvalidArgumentException("Invalid billing cycle [{$cycleValue}].");
        }

        $customIntervalDays = self::normalizeCustomInterval(
            $cycle,
            $data['custom_interval_days'] ?? null,
        );

        return new self(
            billingCycle: $cycle,
            price: self::money($data['price'] ?? null, 'price'),
            setupFee: self::money($data['setup_fee'] ?? 0, 'setup_fee'),
            isEnabled: filter_var($data['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            customIntervalDays: $customIntervalDays,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'billing_cycle' => $this->billingCycle->value,
            'custom_interval_days' => $this->customIntervalDays,
            'price' => $this->price,
            'setup_fee' => $this->setupFee,
            'is_enabled' => $this->isEnabled,
        ];
    }

    private static function normalizeCustomInterval(BillingCycle $cycle, mixed $value): ?int
    {
        if ($cycle->requiresCustomInterval()) {
            if ($value === null || $value === '') {
                throw new InvalidArgumentException(
                    'Custom billing cycles require custom_interval_days.',
                );
            }

            if (! is_numeric($value) || (int) $value < 1) {
                throw new InvalidArgumentException(
                    'custom_interval_days must be a positive integer.',
                );
            }

            return (int) $value;
        }

        if ($value !== null && $value !== '') {
            throw new InvalidArgumentException(
                'custom_interval_days is only allowed for custom billing cycles.',
            );
        }

        return null;
    }

    private static function money(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("The {$field} is required.");
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("The {$field} must be numeric.");
        }

        $amount = (float) $value;

        if ($amount < 0) {
            throw new InvalidArgumentException("The {$field} cannot be negative.");
        }

        return number_format($amount, 2, '.', '');
    }
}
