<?php

namespace Core\Automation\Models;

use Core\Automation\Enums\AutomationLogStatus;
use Database\Factories\AutomationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationLog extends Model
{
    /** @use HasFactory<AutomationLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'automation_rule_id',
        'trigger_event',
        'status',
        'attempt',
        'payload',
        'result',
        'error_message',
        'idempotency_key',
        'next_retry_at',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AutomationLogStatus::class,
            'attempt' => 'integer',
            'payload' => 'array',
            'result' => 'array',
            'next_retry_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AutomationLogFactory
    {
        return AutomationLogFactory::new();
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<AutomationRule, $this>
     */
    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class);
    }
}
