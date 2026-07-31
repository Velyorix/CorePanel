<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexServiceRequest;
use Core\Sync\Enums\SyncLogSubject;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceAccessService;
use Core\Services\Services\ServiceActionLogService;
use Core\Services\Services\ServiceQueryService;
use Core\Sync\Services\SyncLogQueryService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceQueryService $queries,
        private readonly ServiceAccessService $access,
        private readonly ServiceActionLogService $actionLogs,
        private readonly SyncLogQueryService $syncLogs,
    ) {
    }

    public function index(IndexServiceRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.services.index', [
            'services' => $this->queries->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => ServiceStatus::cases(),
        ]);
    }

    public function show(Service $service): View
    {
        Gate::authorize('view', $service);

        $service->load(['client.owner', 'product', 'order']);

        return view('admin.services.show', [
            'service' => $service,
            'access' => $this->access->for($service),
            'actionLogs' => $this->actionLogs->forService($service, limit: 50),
            'syncLogs' => $this->syncLogs->forSubject(SyncLogSubject::Service, (int) $service->id, limit: 20),
            'allowedActions' => ServiceAction::allowedFor($service->status),
            'canSync' => filled($service->module)
                && filled($service->external_id)
                && in_array($service->status, [ServiceStatus::Active, ServiceStatus::Suspended], true),
        ]);
    }
}
