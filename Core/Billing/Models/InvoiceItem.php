<?php

namespace Core\Billing\Models;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'product_id',
        'service_id',
        'description',
        'product_name',
        'product_slug',
        'billing_cycle',
        'custom_interval_days',
        'quantity',
        'options',
        'addons',
        'config_data',
        'unit_price',
        'setup_fee',
        'tax_amount',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'custom_interval_days' => 'integer',
            'quantity' => 'integer',
            'options' => 'array',
            'addons' => 'array',
            'config_data' => 'array',
            'unit_price' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }
}
