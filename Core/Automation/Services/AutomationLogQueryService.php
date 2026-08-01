<?php

namespace Core\Automation\Services;

use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AutomationLogQueryService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     status?: AutomationLogStatus|null,
     *     trigger_event?: string|null,
     *     workflow_id?: int|null,
     *     automation_rule_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $trigger = isset($filters['trigger_event']) ? trim((string) $filters['trigger_event']) : null;
        $workflowId = $filters['workflow_id'] ?? null;
        $ruleId = $filters['automation_rule_id'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'created_at', 'status', 'trigger_event', 'attempt', 'finished_at'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        return AutomationLog::query()
            ->with(['workflow', 'automationRule'])
            ->when($status instanceof AutomationLogStatus, fn ($q) => $q->where('status', $status))
            ->when($trigger !== null && $trigger !== '', fn ($q) => $q->where('trigger_event', $trigger))
            ->when(is_int($workflowId) && $workflowId > 0, fn ($q) => $q->where('workflow_id', $workflowId))
            ->when(is_int($ruleId) && $ruleId > 0, fn ($q) => $q->where('automation_rule_id', $ruleId))
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested->where('trigger_event', 'like', '%'.$search.'%')
                        ->orWhere('error_message', 'like', '%'.$search.'%')
                        ->orWhere('idempotency_key', 'like', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $nested->orWhere('id', (int) $search)
                            ->orWhere('workflow_id', (int) $search)
                            ->orWhere('automation_rule_id', (int) $search);
                    }
                });
            })
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForAdmin(int $id): AutomationLog
    {
        return AutomationLog::query()
            ->with(['workflow', 'automationRule'])
            ->findOrFail($id);
    }
}
