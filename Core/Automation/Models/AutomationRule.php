<?php

namespace Core\Automation\Models;

use Database\Factories\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    /** @use HasFactory<AutomationRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'condition_json',
        'action_json',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition_json' => 'array',
            'action_json' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): AutomationRuleFactory
    {
        return AutomationRuleFactory::new();
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
}
