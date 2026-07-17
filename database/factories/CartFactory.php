<?php

namespace Database\Factories;

use Core\Clients\Models\Client;
use Core\Orders\Enums\CartStatus;
use Core\Orders\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'session_id' => fake()->unique()->sha1(),
            'status' => CartStatus::Open,
            'currency' => 'EUR',
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => CartStatus::Open,
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (): array => [
            'status' => CartStatus::Abandoned,
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (): array => [
            'status' => CartStatus::Converted,
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'client_id' => $client->id,
            'session_id' => null,
        ]);
    }

    public function forSession(string $sessionId): static
    {
        return $this->state(fn (): array => [
            'client_id' => null,
            'session_id' => $sessionId,
        ]);
    }
}
