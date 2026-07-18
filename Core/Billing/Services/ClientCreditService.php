<?php

namespace Core\Billing\Services;

use Core\Auth\Models\User;
use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Billing\Exceptions\InsufficientClientCreditException;
use Core\Billing\Exceptions\InvalidClientCreditException;
use Core\Billing\Models\ClientCreditTransaction;
use Core\Clients\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Client prepaid credit wallet: add, deduct, balance, history.
 */
class ClientCreditService
{
    public function add(
        Client $client,
        string $amount,
        ClientCreditTransactionType $type = ClientCreditTransactionType::Add,
        ?string $description = null,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        ?User $createdBy = null,
        string $currency = 'EUR',
    ): ClientCreditTransaction {
        if (! $type->increasesBalance()) {
            throw new InvalidClientCreditException(
                'add() only accepts credit types that increase the balance.',
            );
        }

        return $this->apply(
            $client,
            $type,
            $amount,
            $description,
            $reference,
            $idempotencyKey,
            $createdBy,
            $currency,
        );
    }

    public function deduct(
        Client $client,
        string $amount,
        ?string $description = null,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        ?User $createdBy = null,
        string $currency = 'EUR',
    ): ClientCreditTransaction {
        return $this->apply(
            $client,
            ClientCreditTransactionType::Deduct,
            $amount,
            $description,
            $reference,
            $idempotencyKey,
            $createdBy,
            $currency,
        );
    }

    public function balance(Client $client): string
    {
        $fresh = Client::query()->whereKey($client->id)->first();

        return $this->money((float) ($fresh?->credit_balance ?? 0));
    }

    /**
     * @return Collection<int, ClientCreditTransaction>
     */
    public function history(Client $client, int $limit = 50): Collection
    {
        return ClientCreditTransaction::query()
            ->where('client_id', $client->id)
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    public function hasSufficientBalance(Client $client, string $amount): bool
    {
        return (float) $this->balance($client) + 0.00001 >= (float) $this->money((float) $amount);
    }

    private function apply(
        Client $client,
        ClientCreditTransactionType $type,
        string $amount,
        ?string $description,
        ?string $reference,
        ?string $idempotencyKey,
        ?User $createdBy,
        string $currency,
    ): ClientCreditTransaction {
        $resolvedAmount = $this->money((float) $amount);

        if ((float) $resolvedAmount <= 0) {
            throw new InvalidClientCreditException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $client,
            $type,
            $resolvedAmount,
            $description,
            $reference,
            $idempotencyKey,
            $createdBy,
            $currency,
        ): ClientCreditTransaction {
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = ClientCreditTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $locked = Client::query()
                ->whereKey($client->id)
                ->lockForUpdate()
                ->firstOrFail();

            $current = $this->money((float) $locked->credit_balance);

            if ($type->increasesBalance()) {
                $newBalance = $this->money((float) $current + (float) $resolvedAmount);
            } else {
                if ((float) $current + 0.00001 < (float) $resolvedAmount) {
                    throw InsufficientClientCreditException::forAmount($resolvedAmount, $current);
                }

                $newBalance = $this->money((float) $current - (float) $resolvedAmount);
            }

            $transaction = ClientCreditTransaction::query()->create([
                'client_id' => $locked->id,
                'created_by' => $createdBy?->id,
                'type' => $type,
                'amount' => $resolvedAmount,
                'balance_after' => $newBalance,
                'currency' => $currency !== '' ? strtoupper($currency) : 'EUR',
                'reference' => $reference,
                'description' => $description,
                'idempotency_key' => ($idempotencyKey !== null && $idempotencyKey !== '')
                    ? $idempotencyKey
                    : null,
            ]);

            $locked->forceFill([
                'credit_balance' => $newBalance,
            ])->save();

            return $transaction->fresh(['client', 'creator']) ?? $transaction;
        });
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
