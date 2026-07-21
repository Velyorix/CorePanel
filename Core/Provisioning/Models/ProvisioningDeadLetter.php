<?php

namespace Core\Provisioning\Models;

use App\Models\User;
use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Services\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningDeadLetter extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'module',
        'status',
        'exception_class',
        'exception_message',
        'attempts',
        'payload',
        'failed_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProvisioningDeadLetterStatus::class,
            'attempts' => 'integer',
            'payload' => 'array',
            'failed_at' => 'datetime',
            'resolved_at' => 'datetime',
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
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
