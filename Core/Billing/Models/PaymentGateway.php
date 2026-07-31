<?php

namespace Core\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted payment gateway installation (enabled state + encrypted config).
 */
class PaymentGateway extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'enabled',
        'config',
        'sort_order',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'encrypted:array',
            'sort_order' => 'integer',
        ];
    }
}
