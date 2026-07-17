<?php

namespace Core\Products\Models;

use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'type',
        'module',
        'status',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Pricing tiers keyed by billing cycle.
     *
     * @return HasMany<ProductPricing, $this>
     */
    public function pricing(): HasMany
    {
        return $this->hasMany(ProductPricing::class);
    }

    /**
     * Enabled pricing tiers only.
     *
     * @return HasMany<ProductPricing, $this>
     */
    public function enabledPricing(): HasMany
    {
        return $this->pricing()->where('is_enabled', true);
    }

    public function pricingFor(BillingCycle $cycle): ?ProductPricing
    {
        $this->loadMissing('pricing');

        return $this->pricing->first(
            fn (ProductPricing $tier): bool => $tier->billing_cycle === $cycle,
        );
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
