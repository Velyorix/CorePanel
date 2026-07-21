<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexSyncLogRequest;
use Core\Services\Models\Service;
use Core\Sync\Models\SyncLog;
use Core\Sync\Services\SyncLogQueryService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SyncLogController extends Controller
{
    public function __construct(
        private readonly SyncLogQueryService $logs,
    ) {
    }

    public function index(IndexSyncLogRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.sync-logs.index', [
            'logs' => $this->logs->paginateForAdmin($filters),
            'filters' => $filters,
            'subjectTypes' => \Core\Sync\Enums\SyncLogSubject::cases(),
            'outcomes' => \Core\Sync\Enums\SyncLogOutcome::cases(),
        ]);
    }

    public function show(SyncLog $syncLog): View
    {
        Gate::authorize('viewAny', Service::class);

        $log = $this->logs->findForAdmin($syncLog->id);

        return view('admin.sync-logs.show', [
            'log' => $log,
        ]);
    }
}
