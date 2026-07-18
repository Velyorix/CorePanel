<?php

namespace Core\Billing\Models;

use Core\Auth\Models\User;
use Core\Billing\Enums\ClientCreditTransactionType;
use Core\Clients\Models\Client;
use Database\Factories\ClientCreditTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCreditTransaction extends Model
{
    /** @use HasFactory<ClientCreditTransactionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'created_by',
        'type',
        'amount',
        'balance_after',
        'currency',
        'reference',
        'description',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientCreditTransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): ClientCreditTransactionFactory
    {
        return ClientCreditTransactionFactory::new();
    }
}
