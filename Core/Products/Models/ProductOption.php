<?php

namespace Core\Products\Models;

use Core\Products\Enums\ProductOptionType;
use Database\Factories\ProductOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOption extends Model
{
    /** @use HasFactory<ProductOptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'key',
        'name',
        'type',
        'required',
        'sort_order',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductOptionType::class,
            'required' => 'boolean',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function choices(): array
    {
        if (! $this->type->hasChoices()) {
            return [];
        }

        $choices = $this->config['choices'] ?? [];

        return is_array($choices) ? array_values($choices) : [];
    }

    protected static function newFactory(): ProductOptionFactory
    {
        return ProductOptionFactory::new();
    }
}
