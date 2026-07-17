<?php

namespace Core\Products\Models;

use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductModuleCapability;
use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'module_capabilities',
        'status',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'module_capabilities' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOfType(Builder $query, ProductType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeCatalog(Builder $query): Builder
    {
        return $query->whereIn(
            'type',
            array_map(
                static fn (ProductType $type): string => $type->value,
                ProductType::catalogTypes(),
            ),
        );
    }

    /**
     * Products linked to a provider module.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWithModule(Builder $query, ?string $module = null): Builder
    {
        if ($module === null) {
            return $query->whereNotNull('module');
        }

        return $query->where('module', $module);
    }

    public function hasModule(): bool
    {
        return filled($this->module);
    }

    public function usesModule(string $module): bool
    {
        return $this->module === $module;
    }

    /**
     * @return list<string>
     */
    public function requiredCapabilities(): array
    {
        $capabilities = $this->module_capabilities ?? [];

        return is_array($capabilities) ? array_values($capabilities) : [];
    }

    public function requiresCapability(string|ProductModuleCapability $capability): bool
    {
        $value = $capability instanceof ProductModuleCapability
            ? $capability->value
            : $capability;

        return in_array($value, $this->requiredCapabilities(), true);
    }

    public function requiresAllCapabilities(string ...$capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if (! $this->requiresCapability($capability)) {
                return false;
            }
        }

        return $capabilities !== [];
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

    /**
     * Configurable options for the product configurator.
     *
     * @return HasMany<ProductOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    /**
     * Required configurable options only.
     *
     * @return HasMany<ProductOption, $this>
     */
    public function requiredOptions(): HasMany
    {
        return $this->options()->where('required', true);
    }

    /**
     * Billable addons attached to this product (separate billing from base pricing).
     *
     * @return HasMany<ProductAddon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(ProductAddon::class)->orderBy('sort_order');
    }

    /**
     * Enabled addons only.
     *
     * @return HasMany<ProductAddon, $this>
     */
    public function enabledAddons(): HasMany
    {
        return $this->addons()->where('is_enabled', true);
    }

    public function pricingFor(BillingCycle $cycle): ?ProductPricing
    {
        $this->loadMissing('pricing');

        return $this->pricing->first(
            fn (ProductPricing $tier): bool => $tier->billing_cycle === $cycle,
        );
    }

    public function optionByKey(string $key): ?ProductOption
    {
        $this->loadMissing('options');

        return $this->options->first(
            fn (ProductOption $option): bool => $option->key === $key,
        );
    }

    public function addonByKey(string $key): ?ProductAddon
    {
        $this->loadMissing('addons');

        return $this->addons->first(
            fn (ProductAddon $addon): bool => $addon->key === $key,
        );
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
