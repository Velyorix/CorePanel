<?php

namespace Core\License\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'instance_id',
        'license_remote_id',
        'status',
        'instance_label',
        'domain',
        'product_type',
        'expires_at',
        'last_validated_at',
        'last_seen_at',
        'entitlements',
        'metadata',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'entitlements' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

