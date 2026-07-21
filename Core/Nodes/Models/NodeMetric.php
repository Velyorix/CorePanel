<?php

namespace Core\Nodes\Models;

use Core\Nodes\Enums\NodeMetricStatus;
use Database\Factories\NodeMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeMetric extends Model
{
    /** @use HasFactory<NodeMetricFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'node_id',
        'current_services',
        'max_services',
        'cpu_usage',
        'ram_usage',
        'disk_usage',
        'network_in',
        'network_out',
        'load_average',
        'capacity_available',
        'status',
        'payload',
        'collected_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_services' => 'integer',
            'max_services' => 'integer',
            'cpu_usage' => 'decimal:2',
            'ram_usage' => 'decimal:2',
            'disk_usage' => 'decimal:2',
            'network_in' => 'decimal:2',
            'network_out' => 'decimal:2',
            'load_average' => 'decimal:2',
            'capacity_available' => 'boolean',
            'status' => NodeMetricStatus::class,
            'payload' => 'array',
            'collected_at' => 'datetime',
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

    protected static function newFactory(): NodeMetricFactory
    {
        return NodeMetricFactory::new();
    }
}
