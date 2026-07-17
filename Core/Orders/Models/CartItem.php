<?php

namespace Core\Orders\Models;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'billing_cycle',
        'custom_interval_days',
        'quantity',
        'options',
        'addons',
        'config_data',
        'unit_price',
        'setup_fee',
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
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Fingerprint used to merge identical configuration lines.
     */
    public function configurationFingerprint(): string
    {
        return hash('sha256', json_encode([
            'product_id' => $this->product_id,
            'billing_cycle' => $this->billing_cycle->value,
            'custom_interval_days' => $this->custom_interval_days,
            'options' => $this->normalizePayload($this->options),
            'addons' => $this->normalizePayload($this->addons),
            'config_data' => $this->normalizePayload($this->config_data),
        ], JSON_THROW_ON_ERROR));
    }

    public function lineSubtotal(): string
    {
        $unit = (float) ($this->unit_price ?? 0);
        $setup = (float) ($this->setup_fee ?? 0);

        return number_format(($unit * $this->quantity) + $setup, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $payload
     * @return array<string, mixed>|list<mixed>
     */
    private function normalizePayload(?array $payload): array
    {
        if ($payload === null || $payload === []) {
            return [];
        }

        $normalized = $payload;
        ksort($normalized);

        return $normalized;
    }

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }
}
