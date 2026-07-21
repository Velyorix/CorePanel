<?php

namespace Core\Nodes\Models;

use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Products\Models\ProductProvisioningRules;
use Database\Factories\NodeGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NodeGroup extends Model
{
    /** @use HasFactory<NodeGroupFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'key',
        'location',
        'type',
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
            'type' => NodeGroupType::class,
            'status' => NodeGroupStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<ProductProvisioningRules, $this>
     */
    public function provisioningRules(): HasMany
    {
        return $this->hasMany(ProductProvisioningRules::class);
    }

    /**
     * @return HasMany<Node, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function isActive(): bool
    {
        return $this->status === NodeGroupStatus::Active;
    }

    /**
     * @param  Builder<NodeGroup>  $query
     * @return Builder<NodeGroup>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', NodeGroupStatus::Active->value);
    }

    /**
     * @param  Builder<NodeGroup>  $query
     * @return Builder<NodeGroup>
     */
    public function scopeOfType(Builder $query, NodeGroupType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    protected static function newFactory(): NodeGroupFactory
    {
        return NodeGroupFactory::new();
    }
}
