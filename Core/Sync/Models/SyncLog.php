<?php

namespace Core\Sync\Models;

use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_type',
        'subject_id',
        'module',
        'outcome',
        'message',
        'divergences',
        'resolutions',
        'payload',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => SyncLogSubject::class,
            'outcome' => SyncLogOutcome::class,
            'divergences' => 'array',
            'resolutions' => 'array',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
