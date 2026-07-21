<?php

namespace Core\Nodes\Models;

use Core\Nodes\Enums\NodeClusterStatus;
use Database\Factories\NodeClusterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NodeCluster extends Model
{
    /** @use HasFactory<NodeClusterFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'key',
        'location',
        'description',
        'status',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NodeClusterStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<NodeClusterMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(NodeClusterMember::class);
    }

    /**
     * @return BelongsToMany<Node, $this>
     */
    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'node_cluster_members')
            ->withPivot(['sort_order', 'weight'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function isActive(): bool
    {
        return $this->status === NodeClusterStatus::Active;
    }

    /**
     * @param  Builder<NodeCluster>  $query
     * @return Builder<NodeCluster>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', NodeClusterStatus::Active->value);
    }

    protected static function newFactory(): NodeClusterFactory
    {
        return NodeClusterFactory::new();
    }
}
