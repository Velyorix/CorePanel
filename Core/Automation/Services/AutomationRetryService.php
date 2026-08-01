<?php

namespace Core\Automation\Services;

use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Process due automation_logs in retrying status.
 */
class AutomationRetryService
{
    public function __construct(
        private readonly WorkflowEngine $workflows,
        private readonly RulesEngine $rules,
    ) {
    }

    /**
     * @return Collection<int, AutomationLog>
     */
    public function processDue(?int $limit = null): Collection
    {
        $limit ??= (int) config('corepanel.automation.scheduler.retry_limit', 25);
        $limit = max(1, $limit);

        $logs = AutomationLog::query()
            ->with(['workflow', 'automationRule'])
            ->where('status', AutomationLogStatus::Retrying)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = collect();

        foreach ($logs as $log) {
            try {
                $processed->push($this->retry($log));
            } catch (Throwable $exception) {
                Log::warning('automation.retry.process_failed', [
                    'log_id' => $log->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($processed->isNotEmpty()) {
            Log::info('automation.retry.processed', ['count' => $processed->count()]);
        }

        return $processed;
    }

    public function retry(AutomationLog $log): AutomationLog
    {
        if ($log->status !== AutomationLogStatus::Retrying) {
            throw new RuntimeException("Automation log [{$log->id}] is not awaiting retry.");
        }

        if ($log->workflow_id !== null) {
            return $this->workflows->retry($log);
        }

        if ($log->automation_rule_id !== null) {
            return $this->rules->retry($log);
        }

        throw new RuntimeException("Automation log [{$log->id}] has no workflow or rule to retry.");
    }
}
