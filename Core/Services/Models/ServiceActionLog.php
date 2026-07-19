<?php

namespace Core\Services\Models;

use Core\Auth\Models\User;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceActionLog extends Model
{
    public $timestamps = false;

    protected $table = 'service_actions_log';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'action',
        'status',
        'response',
        'performed_by',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ServiceAction::class,
            'status' => ServiceActionLogStatus::class,
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
