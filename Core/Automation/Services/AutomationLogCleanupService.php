<?php

namespace Core\Automation\Services;

use Core\Automation\Models\AutomationLog;
use Illuminate\Support\Facades\Log;

class AutomationLogCleanupService
{
    public function prune(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? (int) config('corepanel.automation.scheduler.log_retention_days', 30);

        if ($days < 1) {
            return 0;
        }

        $cutoff = now()->subDays($days);

        $deleted = AutomationLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        if ($deleted > 0) {
            Log::info('automation.cleanup.logs', [
                'deleted' => $deleted,
                'retention_days' => $days,
                'cutoff' => $cutoff->toIso8601String(),
            ]);
        }

        return $deleted;
    }
}
