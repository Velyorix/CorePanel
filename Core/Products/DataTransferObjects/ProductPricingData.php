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
    ) {
    }

    /**
     * @param  array{
     *     billing_cycle?: string,
     *     price?: mixed,
     *     setup_fee?: mixed,
     *     is_enabled?: mixed
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $cycleValue = (string) ($data['billing_cycle'] ?? '');
        $cycle = BillingCycle::tryFrom($cycleValue);

        if ($cycle === null) {
            throw new InvalidArgumentException("Invalid billing cycle [{$cycleValue}].");
        }

        return new self(
            billingCycle: $cycle,
            price: self::money($data['price'] ?? null, 'price'),
            setupFee: self::money($data['setup_fee'] ?? 0, 'setup_fee'),
            isEnabled: filter_var($data['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'billing_cycle' => $this->billingCycle->value,
            'price' => $this->price,
            'setup_fee' => $this->setupFee,
            'is_enabled' => $this->isEnabled,
        ];
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
