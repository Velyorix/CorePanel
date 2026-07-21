<?php

namespace Core\Nodes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeGroupRelation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'node_id',
        'node_group_id',
        'is_primary',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
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
     * @return BelongsTo<NodeGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(NodeGroup::class, 'node_group_id');
    }
}
