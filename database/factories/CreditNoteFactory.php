<?php

namespace Database\Factories;

use Core\Billing\Enums\CreditNoteStatus;
use Core\Billing\Models\CreditNote;
use Core\Billing\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'credit_note_number' => null,
            'invoice_id' => Invoice::factory()->paid(),
            'client_id' => 0,
            'created_by' => null,
            'currency' => 'EUR',
            'amount' => '10.00',
            'status' => CreditNoteStatus::Draft,
            'settlement' => null,
            'payment_id' => null,
            'reason' => fake()->optional()->sentence(),
            'notes' => null,
            'issued_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CreditNote $creditNote): void {
            $invoice = $creditNote->invoice_id
                ? Invoice::query()->find($creditNote->invoice_id)
                : null;

            if ($invoice !== null) {
                $creditNote->client_id = $invoice->client_id;
                $creditNote->currency = $invoice->currency ?? 'EUR';
            }
        });
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => CreditNoteStatus::Draft,
            'credit_note_number' => null,
            'issued_at' => null,
            'settlement' => null,
            'payment_id' => null,
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => CreditNoteStatus::Issued,
            'credit_note_number' => 'CN-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'issued_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => CreditNoteStatus::Cancelled,
            'credit_note_number' => null,
            'issued_at' => null,
        ]);
    }
}
