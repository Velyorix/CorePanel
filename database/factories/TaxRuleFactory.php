<?php

namespace Database\Factories;

use Core\Billing\Enums\TaxRuleType;
use Core\Billing\Models\TaxRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRule>
 */
class TaxRuleFactory extends Factory
{
    protected $model = TaxRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country' => 'FR',
            'rate' => '0.2000',
            'type' => TaxRuleType::Standard,
            'active' => true,
        ];
    }

    public function frStandard(): static
    {
        return $this->state(fn (): array => [
            'country' => 'FR',
            'rate' => '0.2000',
            'type' => TaxRuleType::Standard,
            'active' => true,
        ]);
    }

    public function deStandard(): static
    {
        return $this->state(fn (): array => [
            'country' => 'DE',
            'rate' => '0.1900',
            'type' => TaxRuleType::Standard,
            'active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
