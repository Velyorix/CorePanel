<?php

namespace Core\Modules\Models;

use Illuminate\Database\Eloquent\Model;

class InstalledModule extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'version',
        'enabled',
        'config',
        'checksum',
        'signature',
        'path',
        'installed_at',
        'enabled_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
