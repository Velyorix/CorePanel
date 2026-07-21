<?php

namespace Core\Provisioning\Models;

use Core\Provisioning\Enums\ProviderResourceType;
use Core\Services\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderResourceMapping extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'module',
        'external_id',
        'resource_type',
        'metadata',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_type' => ProviderResourceType::class,
            'metadata' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
