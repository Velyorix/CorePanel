<?php

namespace Database\Factories;

use Core\Billing\Models\Quote;
use Core\Billing\Models\QuoteItem;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5, 50);
        $setupFee = fake()->randomFloat(2, 0, 10);
        $quantity = fake()->numberBetween(1, 3);
        $name = fake()->words(3, true);

        return [
            'quote_id' => Quote::factory(),
            'product_id' => Product::factory(),
            'description' => $name,
            'product_name' => $name,
            'product_slug' => fake()->unique()->slug(),
            'billing_cycle' => BillingCycle::Monthly,
            'custom_interval_days' => null,
            'quantity' => $quantity,
            'options' => null,
            'addons' => null,
            'config_data' => null,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'setup_fee' => number_format($setupFee, 2, '.', ''),
            'tax_amount' => '0.00',
            'line_total' => number_format(($unitPrice * $quantity) + $setupFee, 2, '.', ''),
        ];
    }
}
