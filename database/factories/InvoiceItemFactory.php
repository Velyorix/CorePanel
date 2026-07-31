<?php

namespace Database\Factories;

use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

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
            'invoice_id' => Invoice::factory(),
            'product_id' => Product::factory(),
            'service_id' => null,
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
