<?php

namespace Core\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'dedupe_key',
        'title',
        'message',
        'variant',
        'metadata',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
