<?php

namespace Core\Nodes\Models;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Providers\DataTransferObjects\NodeConnectionRequest;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'module',
        'hostname',
        'ip_address',
        'api_url',
        'status',
        'max_services',
        'sort_order',
        'node_group_id',
        'credentials',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NodeType::class,
            'status' => NodeStatus::class,
            'max_services' => 'integer',
            'sort_order' => 'integer',
            'credentials' => 'encrypted:array',
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<NodeGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(NodeGroup::class, 'node_group_id');
    }

    /**
     * @return HasMany<NodeGroupRelation, $this>
     */
    public function groupRelations(): HasMany
    {
        return $this->hasMany(NodeGroupRelation::class);
    }

    /**
     * @return BelongsToMany<NodeGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(NodeGroup::class, 'node_group_relations')
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function isSelectable(): bool
    {
        return $this->status->isSelectable();
    }

    public function hasCapacity(): bool
    {
        if ($this->max_services === null) {
            return true;
        }

        return $this->allocatedServicesCount() < $this->max_services;
    }

    public function allocatedServicesCount(): int
    {
        if (array_key_exists('allocated_services_count', $this->attributes)) {
            return (int) $this->attributes['allocated_services_count'];
        }

        return $this->services()
            ->whereNotIn('status', [
                ServiceStatus::Terminated->value,
                ServiceStatus::Cancelled->value,
            ])
            ->count();
    }

    public function toConnectionRequest(): NodeConnectionRequest
    {
        return NodeConnectionRequest::fromArray([
            'id' => (int) $this->id,
            'module' => $this->module,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'ip_address' => $this->ip_address,
            'api_url' => $this->api_url,
            'credentials' => $this->credentials,
            'config' => $this->config,
            'max_services' => $this->max_services,
        ]);
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('status', NodeStatus::Active->value);
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where(function (Builder $builder) use ($module): void {
            $builder
                ->whereNull('module')
                ->orWhere('module', $module);
        });
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function scopeInGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('node_group_id', $groupId);
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    public function scopeWithAllocatedCount(Builder $query): Builder
    {
        return $query->withCount([
            'services as allocated_services_count' => function (Builder $builder): void {
                $builder->whereNotIn('status', [
                    ServiceStatus::Terminated->value,
                    ServiceStatus::Cancelled->value,
                ]);
            },
        ]);
    }

    protected static function newFactory(): NodeFactory
    {
        return NodeFactory::new();
    }
}
