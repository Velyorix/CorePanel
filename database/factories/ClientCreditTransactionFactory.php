<?php

namespace Database\Factories;

use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Billing\Models\ClientCreditTransaction;
use Core\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCreditTransaction>
 */
class ClientCreditTransactionFactory extends Factory
{
    protected $model = ClientCreditTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = number_format(fake()->randomFloat(2, 1, 100), 2, '.', '');

        return [
            'client_id' => Client::factory(),
            'created_by' => null,
            'type' => ClientCreditTransactionType::Add,
            'amount' => $amount,
            'balance_after' => $amount,
            'currency' => 'EUR',
            'reference' => null,
            'description' => fake()->optional()->sentence(),
            'idempotency_key' => null,
        ];
    }

    public function add(): static
    {
        return $this->state(fn (): array => [
            'type' => ClientCreditTransactionType::Add,
        ]);
    }

    public function deduct(): static
    {
        return $this->state(fn (): array => [
            'type' => ClientCreditTransactionType::Deduct,
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn (): array => [
            'type' => ClientCreditTransactionType::Refund,
        ]);
    }
}
