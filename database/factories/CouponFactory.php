<?php

namespace Database\Factories;

use Core\Billing\Enums\CouponAppliesTo;
use Core\Billing\Enums\CouponType;
use Core\Billing\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##')),
            'type' => CouponType::Percent,
            'value' => '10.00',
            'currency' => null,
            'applies_to' => CouponAppliesTo::Order,
            'max_uses' => null,
            'uses_count' => 0,
            'max_uses_per_client' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'client_id' => null,
            'product_ids' => null,
            'recurring_cycles' => null,
            'active' => true,
        ];
    }

    public function percent(string $code, string $value = '10.00'): static
    {
        return $this->state(fn (): array => [
            'code' => strtoupper($code),
            'type' => CouponType::Percent,
            'value' => $value,
            'currency' => null,
        ]);
    }

    public function fixed(string $code, string $value = '5.00', string $currency = 'EUR'): static
    {
        return $this->state(fn (): array => [
            'code' => strtoupper($code),
            'type' => CouponType::Fixed,
            'value' => $value,
            'currency' => $currency,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
