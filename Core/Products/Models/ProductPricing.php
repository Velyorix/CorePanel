<?php

namespace Core\Products\Models;

use Core\Products\Enums\BillingCycle;
use Database\Factories\ProductPricingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPricing extends Model
{
    /** @use HasFactory<ProductPricingFactory> */
    use HasFactory;

    protected $table = 'product_pricing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'billing_cycle',
        'custom_interval_days',
        'price',
        'setup_fee',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'custom_interval_days' => 'integer',
            'price' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function firstPaymentTotal(): string
    {
        return number_format((float) $this->price + (float) $this->setup_fee, 2, '.', '');
    }

    public function periodDays(): int
    {
        return $this->billing_cycle->days($this->custom_interval_days);
    }

    protected static function newFactory(): ProductPricingFactory
    {
        return ProductPricingFactory::new();
    }
}
