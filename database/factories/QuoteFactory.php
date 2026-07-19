<?php

namespace Database\Factories;

use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Models\Quote;
use Core\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_number' => null,
            'client_id' => Client::factory(),
            'created_by' => null,
            'converted_invoice_id' => null,
            'status' => QuoteStatus::Draft,
            'currency' => 'EUR',
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
            'subtotal' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '0.00',
            'valid_until' => null,
            'sent_at' => null,
            'accepted_at' => null,
            'converted_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Draft,
            'quote_number' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Sent,
            'quote_number' => 'QUO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'sent_at' => now(),
            'valid_until' => now()->addDays(30),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Accepted,
            'quote_number' => 'QUO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
            'valid_until' => now()->addDays(30),
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Converted,
            'quote_number' => 'QUO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'sent_at' => now()->subDays(2),
            'accepted_at' => now()->subDay(),
            'converted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Expired,
            'quote_number' => 'QUO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'sent_at' => now()->subDays(40),
            'valid_until' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteStatus::Cancelled,
            'quote_number' => null,
        ]);
    }
}
