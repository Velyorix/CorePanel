<?php

namespace Database\Factories;

use App\Models\User;
use Core\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'vat_number' => fake()->optional()->bothify('BE#########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->countryCode(),
            'postal_code' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
        ];
    }
}
