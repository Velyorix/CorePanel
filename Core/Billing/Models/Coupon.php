<?php

namespace Core\Billing\Models;

use Core\Billing\Enums\CouponAppliesTo;
use Core\Billing\Enums\CouponType;
use Core\Clients\Models\Client;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'type',
        'value',
        'currency',
        'applies_to',
        'max_uses',
        'uses_count',
        'max_uses_per_client',
        'starts_at',
        'expires_at',
        'client_id',
        'product_ids',
        'recurring_cycles',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'applies_to' => CouponAppliesTo::class,
            'value' => 'decimal:2',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'max_uses_per_client' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'product_ids' => 'array',
            'recurring_cycles' => 'integer',
            'active' => 'boolean',
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
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }
}
