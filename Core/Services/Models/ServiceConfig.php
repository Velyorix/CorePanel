<?php

namespace Core\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceConfig extends Model
{
    protected $table = 'service_config';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'data',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
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
