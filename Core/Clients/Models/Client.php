<?php

namespace Core\Clients\Models;

use Core\Auth\Models\User;
use Core\Billing\Models\Quote;
use Core\Clients\Enums\ClientStatus;
use Core\Orders\Models\Cart;
use Core\Orders\Models\Order;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'company_name',
        'vat_number',
        'address',
        'city',
        'country',
        'postal_code',
        'phone',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<ClientUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClientUser::class);
    }

    /**
     * @return HasMany<ClientUserInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(ClientUserInvitation::class);
    }

    /**
     * Internal staff notes (admin only).
     *
     * @return HasMany<ClientNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_users')
            ->withPivot(['role', 'permissions', 'created_at']);
    }

    /**
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }
}
