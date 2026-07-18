<?php

namespace Database\Factories;

use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderSource;
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
            'order_number' => null,
            'client_id' => Client::factory(),
            'created_by' => null,
            'source' => OrderSource::ClientCheckout,
            'cart_id' => null,
            'status' => OrderStatus::Draft,
            'currency' => 'EUR',
            'payment_method' => 'manual_transfer',
            'coupon_code' => null,
            'coupon_id' => null,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'company_name' => fake()->optional()->company(),
            'vat_number' => null,
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'FR',
            'postal_code' => fake()->postcode(),
            'phone' => fake()->optional()->phoneNumber(),
            'notes' => null,
            'subtotal_recurring' => '0.00',
            'subtotal_setup' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '0.00',
            'placed_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::PendingPayment,
            'placed_at' => now(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Paid,
            'placed_at' => now()->subDay(),
            'paid_at' => now(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Cancelled,
            'placed_at' => now()->subDay(),
            'cancelled_at' => now(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'client_id' => $client->id,
        ]);
    }
}
