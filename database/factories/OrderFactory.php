<?php

namespace Database\Factories;

use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'cart_id' => null,
            'status' => OrderStatus::Draft,
            'currency' => 'EUR',
            'payment_method' => 'manual_transfer',
            'coupon_code' => null,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'company_name' => fake()->optional()->company(),
            'vat_number' => null,
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'FR',
            'postal_code' => fake()->postcode(),
            'phone' => fake()->optional()->phoneNumber(),
            'subtotal_recurring' => '0.00',
            'subtotal_setup' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '0.00',
            'placed_at' => null,
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::PendingPayment,
            'placed_at' => now(),
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'client_id' => $client->id,
        ]);
    }
}
