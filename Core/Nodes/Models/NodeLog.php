<?php

namespace Core\Nodes\Models;

use Core\Auth\Models\User;
use Core\Nodes\Enums\NodeLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'node_id',
        'action',
        'status',
        'response',
        'performed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NodeLogStatus::class,
            'response' => 'array',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
