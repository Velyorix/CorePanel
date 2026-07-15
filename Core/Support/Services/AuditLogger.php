<?php

namespace Core\Support\Services;

use Core\Support\Models\AuditLog;

class AuditLogger
{
    public function record(
        string $action,
        ?int $actorId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
