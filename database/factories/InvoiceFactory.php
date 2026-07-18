<?php

namespace Database\Factories;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => null,
            'client_id' => Client::factory(),
            'order_id' => null,
            'created_by' => null,
            'status' => InvoiceStatus::Draft,
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
            'issued_at' => null,
            'due_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Draft,
            'invoice_number' => null,
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Unpaid,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Overdue,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'issued_at' => now()->subDays(30),
            'due_at' => now()->subDays(7),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Paid,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'issued_at' => now()->subDays(7),
            'due_at' => now()->addDays(7),
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Cancelled,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'issued_at' => now()->subDay(),
            'cancelled_at' => now(),
        ]);
    }
}
