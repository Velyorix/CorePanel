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

    protected static function newFactory(): ProductPricingFactory
    {
        return ProductPricingFactory::new();
    }
}
