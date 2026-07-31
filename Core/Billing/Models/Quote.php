<?php

namespace Core\Billing\Models;

use Core\Auth\Models\User;
use Core\Billing\Enums\QuoteStatus;
use Core\Clients\Models\Client;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quote_number',
        'client_id',
        'created_by',
        'converted_invoice_id',
        'status',
        'currency',
        'contact_name',
        'contact_email',
        'company_name',
        'vat_number',
        'address',
        'city',
        'country',
        'postal_code',
        'phone',
        'notes',
        'subtotal',
        'tax_amount',
        'total_amount',
        'valid_until',
        'sent_at',
        'accepted_at',
        'converted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'valid_until' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'converted_at' => 'datetime',
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

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('id');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isConvertible(): bool
    {
        return $this->status->isConvertible() && ! $this->isPastValidUntil();
    }

    public function isPastValidUntil(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    protected static function newFactory(): QuoteFactory
    {
        return QuoteFactory::new();
    }
}
