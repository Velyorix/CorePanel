<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientServiceRequest;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceAccessService;
use Core\Services\Services\ServiceActionLogService;
use Core\Services\Services\ServiceQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceQueryService $queries,
        private readonly ServiceAccessService $access,
        private readonly ServiceActionLogService $actionLogs,
    ) {
    }

    public function index(IndexClientServiceRequest $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null) {
            return view('client.services.index', [
                'services' => null,
                'filters' => $request->filters(),
                'statuses' => ServiceStatus::cases(),
                'clientMissing' => true,
            ]);
        }

        $filters = $request->filters();

        return view('client.services.index', [
            'services' => $this->queries->paginateForClient($client, $filters),
            'filters' => $filters,
            'statuses' => ServiceStatus::cases(),
            'clientMissing' => false,
        ]);
    }

    public function show(Request $request, Service $service): View
    {
        abort_unless($request->user()?->can('client.services.view') ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $service->client_id === $client->id,
            404,
        );

        $service->load(['product', 'order']);

        return view('client.services.show', [
            'service' => $service,
            'access' => $this->access->for($service),
            'actionLogs' => $this->actionLogs->forService($service, limit: 20),
            'allowedActions' => ServiceAction::allowedForClient($service->status),
            'canManage' => $request->user()?->can('client.services.manage') ?? false,
        ]);
    }

    private function resolveClient(Request $request): ?Client
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->clients()->orderBy('clients.id')->first()
            ?? $user->ownedClients()->orderBy('id')->first();
    }
}
