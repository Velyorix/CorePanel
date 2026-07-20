<?php

namespace Core\Services\Models;

use Core\Clients\Models\Client;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Services\Enums\ServiceStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'product_id',
        'order_id',
        'order_item_id',
        'status',
        'module',
        'external_id',
        'billing_cycle',
        'custom_interval_days',
        'config_data',
        'ip_address',
        'hostname',
        'node_id',
        'started_at',
        'ended_at',
        'renewal_date',
        'next_billing_date',
        'provisioned_at',
        'suspended_at',
        'terminated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            'billing_cycle' => BillingCycle::class,
            'config_data' => 'array',
            'custom_interval_days' => 'integer',
            'node_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'renewal_date' => 'datetime',
            'next_billing_date' => 'datetime',
            'provisioned_at' => 'datetime',
            'suspended_at' => 'datetime',
            'terminated_at' => 'datetime',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return HasMany<ServiceActionLog, $this>
     */
    public function actionLogs(): HasMany
    {
        return $this->hasMany(ServiceActionLog::class)->orderBy('id');
    }

    /**
     * Encrypted runtime config (credentials, IP, hostname, metadata).
     *
     * @return HasOne<ServiceConfig, $this>
     */
    public function config(): HasOne
    {
        return $this->hasOne(ServiceConfig::class);
    }

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }
}
