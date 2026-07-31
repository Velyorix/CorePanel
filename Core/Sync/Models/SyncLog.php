<?php

namespace Core\Sync\Models;

use Core\Nodes\Models\Node;
use Core\Services\Models\Service;
use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'subject_id');
    }

    /**
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'subject_id');
    }

    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            SyncLogSubject::Service => $this->relationLoaded('service') && $this->service
                ? ($this->service->hostname ?: __('Service #'.$this->subject_id))
                : __('Service #'.$this->subject_id),
            SyncLogSubject::Node => $this->relationLoaded('node') && $this->node
                ? $this->node->name
                : __('Node #'.$this->subject_id),
        };
    }

    public function subjectUrl(): ?string
    {
        return match ($this->subject_type) {
            SyncLogSubject::Service => $this->service
                ? route('admin.services.show', $this->service)
                : null,
            SyncLogSubject::Node => $this->node
                ? route('admin.nodes.show', $this->node)
                : null,
        };
    }
}
