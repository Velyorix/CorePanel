<?php

namespace Core\Products\DataTransferObjects;

use Core\Products\Enums\BillingCycle;
use Illuminate\Support\Str;
use InvalidArgumentException;

readonly class ProductAddonData
{
    public function __construct(
        public string $key,
        public string $name,
        public string $price,
        public BillingCycle $billingCycle,
        public string $setupFee = '0.00',
        public ?string $description = null,
        public bool $isEnabled = true,
        public int $sortOrder = 0,
        public ?int $customIntervalDays = null,
    ) {
    }

    /**
     * @param  array{
     *     key?: string|null,
     *     name?: string|null,
     *     description?: string|null,
     *     price?: mixed,
     *     setup_fee?: mixed,
     *     billing_cycle?: string|null,
     *     is_enabled?: mixed,
     *     sort_order?: int|null,
     *     custom_interval_days?: int|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $name = self::requiredString($data['name'] ?? null, 'name');
        $keyInput = self::nullableString($data['key'] ?? null);
        $key = Str::slug($keyInput ?: $name, '_');

        if ($key === '') {
            throw new InvalidArgumentException('The addon key is required.');
        }

        $cycleValue = (string) ($data['billing_cycle'] ?? '');
        $cycle = BillingCycle::tryFrom($cycleValue);

        if ($cycle === null) {
            throw new InvalidArgumentException("Invalid addon billing cycle [{$cycleValue}].");
        }

        $customIntervalDays = self::normalizeCustomInterval(
            $cycle,
            $data['custom_interval_days'] ?? null,
        );

        return new self(
            key: $key,
            name: $name,
            price: self::money($data['price'] ?? null, 'price'),
            billingCycle: $cycle,
            setupFee: self::money($data['setup_fee'] ?? 0, 'setup_fee'),
            description: self::nullableString($data['description'] ?? null),
            isEnabled: filter_var($data['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            sortOrder: max(0, (int) ($data['sort_order'] ?? 0)),
            customIntervalDays: $customIntervalDays,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'setup_fee' => $this->setupFee,
            'billing_cycle' => $this->billingCycle->value,
            'custom_interval_days' => $this->customIntervalDays,
            'is_enabled' => $this->isEnabled,
            'sort_order' => $this->sortOrder,
        ];
    }

    private static function normalizeCustomInterval(BillingCycle $cycle, mixed $value): ?int
    {
        if ($cycle->requiresCustomInterval()) {
            if ($value === null || $value === '') {
                throw new InvalidArgumentException(
                    'Custom addon billing cycles require custom_interval_days.',
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
            throw new InvalidArgumentException("The addon {$field} is required.");
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("The addon {$field} must be numeric.");
        }

        $amount = (float) $value;

        if ($amount < 0) {
            throw new InvalidArgumentException("The addon {$field} cannot be negative.");
        }

        return number_format($amount, 2, '.', '');
    }

    private static function requiredString(mixed $value, string $field): string
    {
        $string = self::nullableString($value);

        if ($string === null) {
            throw new InvalidArgumentException("The addon {$field} is required.");
        }

        return $string;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
