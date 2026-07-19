<?php

namespace Core\Billing\Models;

use Core\Auth\Models\User;
use Core\Billing\Enums\CreditNoteSettlement;
use Core\Billing\Enums\CreditNoteStatus;
use Core\Clients\Models\Client;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'credit_note_number',
        'invoice_id',
        'client_id',
        'created_by',
        'currency',
        'amount',
        'status',
        'settlement',
        'payment_id',
        'reason',
        'notes',
        'issued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'settlement' => CreditNoteSettlement::class,
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    protected static function newFactory(): CreditNoteFactory
    {
        return CreditNoteFactory::new();
    }
}
