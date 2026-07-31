<?php

namespace Core\Orders\Models;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
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
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }
}
