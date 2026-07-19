<?php

namespace Core\Billing\Models;

use Core\Auth\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\OverdueInvoiceAction;
use Core\Billing\Enums\PaymentStatus;
use Core\Clients\Models\Client;
use Core\Orders\Models\Order;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'client_id',
        'order_id',
        'coupon_id',
        'created_by',
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
        'discount_amount',
        'tax_amount',
        'total_amount',
        'issued_at',
        'due_at',
        'reminder_level',
        'last_reminder_at',
        'reminders_sent',
        'overdue_action',
        'overdue_action_at',
        'paid_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'reminder_level' => 'integer',
            'last_reminder_at' => 'datetime',
            'reminders_sent' => 'integer',
            'overdue_action' => OverdueInvoiceAction::class,
            'overdue_action_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<\Core\Billing\Models\Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function completedPayments(): HasMany
    {
        return $this->payments()->where('status', PaymentStatus::Completed);
    }

    public function amountPaid(): string
    {
        $sum = (float) $this->completedPayments()->sum('amount');

        return number_format(round($sum, 2), 2, '.', '');
    }

    public function amountDue(): string
    {
        $due = max(0, (float) $this->total_amount - (float) $this->amountPaid());

        return number_format(round($due, 2), 2, '.', '');
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->total_amount > 0
            && (float) $this->amountDue() <= 0.00001;
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [InvoiceStatus::Unpaid, InvoiceStatus::Overdue], true)
            && (float) $this->amountDue() > 0;
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }
}
