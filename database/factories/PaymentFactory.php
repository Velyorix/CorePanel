<?php

namespace Database\Factories;

use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory()->unpaid(),
            'client_id' => 0,
            'method' => 'fake',
            'currency' => 'EUR',
            'amount' => '10.00',
            'status' => PaymentStatus::Pending,
            'transaction_id' => null,
            'gateway_reference' => null,
            'notes' => null,
            'paid_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Payment $payment): void {
            $invoice = $payment->invoice_id
                ? Invoice::query()->find($payment->invoice_id)
                : null;

            if ($invoice !== null) {
                $payment->client_id = $invoice->client_id;
                $payment->currency = $invoice->currency ?? 'EUR';
            }
        });
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
            'transaction_id' => 'txn_'.fake()->unique()->numerify('######'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'paid_at' => null,
        ]);
    }
}
