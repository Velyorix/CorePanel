<?php

namespace Core\Nodes\Models;

use Core\Nodes\Enums\NodeHealthState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeHealthCheck extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'node_id',
        'state',
        'latency_ms',
        'message',
        'payload',
        'checked_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => NodeHealthState::class,
            'latency_ms' => 'integer',
            'payload' => 'array',
            'checked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
