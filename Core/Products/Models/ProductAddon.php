<?php

namespace Core\Products\Models;

use Core\Products\Enums\BillingCycle;
use Database\Factories\ProductAddonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAddon extends Model
{
    /** @use HasFactory<ProductAddonFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'key',
        'name',
        'description',
        'price',
        'setup_fee',
        'billing_cycle',
        'custom_interval_days',
        'is_enabled',
        'sort_order',
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
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): ProductAddonFactory
    {
        return ProductAddonFactory::new();
    }
}
