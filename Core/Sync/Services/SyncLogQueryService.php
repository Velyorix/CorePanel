<?php

namespace Core\Sync\Services;

use Core\Nodes\Models\Node;
use Core\Services\Models\Service;
use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Core\Sync\Models\SyncLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SyncLogQueryService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     subject_type?: SyncLogSubject|null,
     *     outcome?: SyncLogOutcome|null,
     *     module?: string|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $subjectType = $filters['subject_type'] ?? null;
        $outcome = $filters['outcome'] ?? null;
        $module = isset($filters['module']) ? trim((string) $filters['module']) : null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'created_at', 'subject_type', 'outcome', 'module'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $query = SyncLog::query()
            ->when($subjectType instanceof SyncLogSubject, fn ($builder) => $builder->where('subject_type', $subjectType))
            ->when($outcome instanceof SyncLogOutcome, fn ($builder) => $builder->where('outcome', $outcome))
            ->when($module !== null && $module !== '', fn ($builder) => $builder->where('module', $module))
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('module', 'like', '%'.$search.'%')
                        ->orWhere('message', 'like', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $nested->orWhere('subject_id', (int) $search);
                    }
                });
            })
            ->orderBy($sort, $dir);

        $paginator = $query->paginate($perPage)->withQueryString();

        $this->hydrateSubjects($paginator->getCollection());

        return $paginator;
    }

    /**
     * @return Collection<int, SyncLog>
     */
    public function forSubject(SyncLogSubject $subjectType, int $subjectId, int $limit = 20): Collection
    {
        $logs = SyncLog::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get();

        $this->hydrateSubjects($logs);

        return $logs;
    }

    public function findForAdmin(int $id): SyncLog
    {
        $log = SyncLog::query()->findOrFail($id);
        $this->hydrateSubjects(new Collection([$log]));

        return $log;
    }

    /**
     * @param  Collection<int, SyncLog>  $logs
     */
    private function hydrateSubjects(Collection $logs): void
    {
        if ($logs->isEmpty()) {
            return;
        }

        $serviceIds = $logs
            ->where('subject_type', SyncLogSubject::Service)
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->all();

        $nodeIds = $logs
            ->where('subject_type', SyncLogSubject::Node)
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->all();

        $services = $serviceIds === []
            ? collect()
            : Service::query()->whereIn('id', $serviceIds)->get()->keyBy('id');

        $nodes = $nodeIds === []
            ? collect()
            : Node::query()->whereIn('id', $nodeIds)->get()->keyBy('id');

        foreach ($logs as $log) {
            match ($log->subject_type) {
                SyncLogSubject::Service => $log->setRelation(
                    'service',
                    $services->get($log->subject_id),
                ),
                SyncLogSubject::Node => $log->setRelation(
                    'node',
                    $nodes->get($log->subject_id),
                ),
            };
        }
    }
}
