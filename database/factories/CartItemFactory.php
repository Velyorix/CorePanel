<?php

namespace Database\Factories;

use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory()->published(),
            'billing_cycle' => BillingCycle::Monthly,
            'custom_interval_days' => null,
            'quantity' => 1,
            'options' => null,
            'addons' => null,
            'config_data' => null,
            'unit_price' => fake()->randomFloat(2, 1, 100),
            'setup_fee' => fake()->randomFloat(2, 0, 20),
        ];
    }

    public function forCart(Cart $cart): static
    {
        return $this->state(fn (): array => [
            'cart_id' => $cart->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'product_id' => $product->id,
        ]);
    }

    public function forCycle(BillingCycle $cycle, ?int $customIntervalDays = null): static
    {
        return $this->state(fn (): array => [
            'billing_cycle' => $cycle,
            'custom_interval_days' => $cycle === BillingCycle::Custom
                ? ($customIntervalDays ?? 45)
                : null,
        ]);
    }
}
