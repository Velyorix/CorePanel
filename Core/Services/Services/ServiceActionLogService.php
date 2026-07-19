<?php

namespace Core\Services\Services;

use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Query helpers for service_actions_log (write path stays on ServiceActionLogger).
 */
class ServiceActionLogService
{
    /**
     * @return Collection<int, ServiceActionLog>
     */
    public function forService(
        Service $service,
        ?ServiceAction $action = null,
        ?ServiceActionLogStatus $status = null,
        ?int $limit = null,
    ): Collection {
        $query = $this->baseQuery($service);

        if ($action !== null) {
            $query->where('action', $action->value);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query->get();
    }

    public function latest(Service $service): ?ServiceActionLog
    {
        return $this->baseQuery($service)->first();
    }

    /**
     * @return Builder<ServiceActionLog>
     */
    private function baseQuery(Service $service): Builder
    {
        return ServiceActionLog::query()
            ->with(['performer'])
            ->where('service_id', $service->id)
            ->orderByDesc('id');
    }
}
