<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Billing\Exceptions\InsufficientClientCreditException;
use Core\Billing\Exceptions\InvalidClientCreditException;
use Core\Billing\Models\ClientCreditTransaction;
use Core\Billing\Services\ClientCreditService;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClientCreditService $credits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credits = app(ClientCreditService::class);
    }

    public function test_client_credit_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(ClientCreditService::class),
            app(ClientCreditService::class),
        );
    }

    public function test_transaction_type_enum_covers_ledger_types(): void
    {
        $this->assertSame(['add', 'deduct', 'refund'], ClientCreditTransactionType::values());
        $this->assertTrue(ClientCreditTransactionType::Add->increasesBalance());
        $this->assertTrue(ClientCreditTransactionType::Refund->increasesBalance());
        $this->assertFalse(ClientCreditTransactionType::Deduct->increasesBalance());
    }

    public function test_add_increases_balance_and_writes_ledger(): void
    {
        $client = Client::factory()->create();

        $transaction = $this->credits->add($client, '10.00', description: 'Manual top-up');

        $this->assertSame(ClientCreditTransactionType::Add, $transaction->type);
        $this->assertSame('10.00', $transaction->amount);
        $this->assertSame('10.00', $transaction->balance_after);
        $this->assertSame('10.00', $this->credits->balance($client));
        $this->assertSame('10.00', $client->fresh()->creditBalance());
        $this->assertSame(1, $client->creditTransactions()->count());
    }

    public function test_deduct_decreases_balance(): void
    {
        $client = Client::factory()->create();
        $this->credits->add($client, '100.00');

        $transaction = $this->credits->deduct($client, '30.00', reference: 'invoice:1');

        $this->assertSame(ClientCreditTransactionType::Deduct, $transaction->type);
        $this->assertSame('30.00', $transaction->amount);
        $this->assertSame('70.00', $transaction->balance_after);
        $this->assertSame('70.00', $this->credits->balance($client));
        $this->assertSame('invoice:1', $transaction->reference);
    }

    public function test_deduct_rejects_insufficient_balance(): void
    {
        $client = Client::factory()->create();
        $this->credits->add($client, '20.00');

        try {
            $this->credits->deduct($client, '20.01');
            $this->fail('Expected InsufficientClientCreditException.');
        } catch (InsufficientClientCreditException $exception) {
            $this->assertStringContainsString('20.01', $exception->getMessage());
        }

        $this->assertSame('20.00', $this->credits->balance($client));
        $this->assertSame(1, ClientCreditTransaction::query()->count());
    }

    public function test_rejects_zero_or_negative_amount(): void
    {
        $client = Client::factory()->create();

        $this->expectException(InvalidClientCreditException::class);

        $this->credits->add($client, '0.00');
    }

    public function test_add_rejects_deduct_type(): void
    {
        $client = Client::factory()->create();

        $this->expectException(InvalidClientCreditException::class);

        $this->credits->add($client, '5.00', ClientCreditTransactionType::Deduct);
    }

    public function test_balance_returns_zero_for_new_client(): void
    {
        $client = Client::factory()->create();

        $this->assertSame('0.00', $this->credits->balance($client));
        $this->assertFalse($this->credits->hasSufficientBalance($client, '0.01'));
        $this->assertTrue($this->credits->hasSufficientBalance($client, '0.00'));
    }

    public function test_history_returns_newest_first(): void
    {
        $client = Client::factory()->create();
        $this->credits->add($client, '10.00');
        $this->credits->add($client, '5.00');
        $this->credits->deduct($client, '3.00');

        $history = $this->credits->history($client, limit: 2);

        $this->assertCount(2, $history);
        $this->assertSame(ClientCreditTransactionType::Deduct, $history->first()->type);
        $this->assertSame('3.00', $history->first()->amount);
        $this->assertSame(ClientCreditTransactionType::Add, $history->get(1)->type);
        $this->assertSame('5.00', $history->get(1)->amount);
    }

    public function test_idempotency_key_returns_existing_transaction(): void
    {
        $client = Client::factory()->create();

        $first = $this->credits->add(
            $client,
            '25.00',
            idempotencyKey: 'topup-001',
        );
        $second = $this->credits->add(
            $client,
            '25.00',
            idempotencyKey: 'topup-001',
        );

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ClientCreditTransaction::query()->count());
        $this->assertSame('25.00', $this->credits->balance($client));
    }

    public function test_refund_type_adds_to_balance(): void
    {
        $client = Client::factory()->create();

        $transaction = $this->credits->add(
            $client,
            '15.50',
            ClientCreditTransactionType::Refund,
            description: 'Refund to credit',
        );

        $this->assertSame(ClientCreditTransactionType::Refund, $transaction->type);
        $this->assertSame('15.50', $this->credits->balance($client));
    }

    public function test_sequential_deducts_do_not_overdraw(): void
    {
        $client = Client::factory()->create();
        $this->credits->add($client, '50.00');

        $this->credits->deduct($client, '30.00');

        $this->expectException(InsufficientClientCreditException::class);
        $this->credits->deduct($client, '30.00');
    }

    public function test_ledger_sum_matches_cached_balance(): void
    {
        $client = Client::factory()->create();
        $this->credits->add($client, '100.00');
        $this->credits->deduct($client, '40.00');
        $this->credits->add($client, '12.50', ClientCreditTransactionType::Refund);

        $signed = ClientCreditTransaction::query()
            ->where('client_id', $client->id)
            ->get()
            ->reduce(function (float $carry, ClientCreditTransaction $row): float {
                return $row->type->increasesBalance()
                    ? $carry + (float) $row->amount
                    : $carry - (float) $row->amount;
            }, 0.0);

        $this->assertSame(
            number_format(round($signed, 2), 2, '.', ''),
            $this->credits->balance($client),
        );
        $this->assertSame('72.50', $this->credits->balance($client));
    }
}
