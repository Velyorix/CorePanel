<?php

namespace Database\Factories;

use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5, 50);
        $setupFee = fake()->randomFloat(2, 0, 10);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'billing_cycle' => BillingCycle::Monthly,
            'custom_interval_days' => null,
            'quantity' => $quantity,
            'options' => null,
            'addons' => null,
            'config_data' => null,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'setup_fee' => number_format($setupFee, 2, '.', ''),
            'line_total' => number_format(($unitPrice * $quantity) + $setupFee, 2, '.', ''),
        ];
    }
}
