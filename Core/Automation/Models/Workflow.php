<?php

namespace Core\Automation\Models;

use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'trigger_event',
        'conditions',
        'steps',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'steps' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): WorkflowFactory
    {
        return WorkflowFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Workflow $workflow): void {
            if (filled($workflow->slug)) {
                return;
            }

            $base = Str::slug((string) $workflow->name);
            $workflow->slug = $base !== '' ? $base : Str::lower(Str::random(8));
        });
    }

    /**
     * @return HasMany<AutomationLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('trigger_event', $event);
    }
}
