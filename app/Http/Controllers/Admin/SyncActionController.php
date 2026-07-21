<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Nodes\Models\Node;
use Core\Services\Models\Service;
use Core\Sync\Services\NodeSyncService;
use Core\Sync\Services\ServiceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SyncActionController extends Controller
{
    public function __construct(
        private readonly ServiceSyncService $serviceSync,
        private readonly NodeSyncService $nodeSync,
    ) {
    }

    public function syncService(Service $service): RedirectResponse
    {
        Gate::authorize('manage', $service);

        $outcome = $this->serviceSync->pollService($service);

        return redirect()
            ->route('admin.services.show', $service)
            ->with($this->flashForServiceOutcome($outcome));
    }

    public function runAll(): RedirectResponse
    {
        Gate::authorize('manage', Service::class);

        if (! Gate::allows('viewAny', Node::class)) {
            abort(403);
        }

        $serviceResult = $this->serviceSync->pollAll();
        $nodeResult = $this->nodeSync->syncAll();

        return redirect()
            ->route('admin.sync-logs.index')
            ->with('status', __('Sync completed: :polled service poll(s), :resolved resolved, :diverged diverged, :failed service failure(s); :synced node sync(s), :nodeFailed node failure(s).', [
                'polled' => $serviceResult->polled,
                'resolved' => $serviceResult->resolved,
                'diverged' => $serviceResult->diverged,
                'failed' => $serviceResult->failed,
                'synced' => $nodeResult->synced,
                'nodeFailed' => $nodeResult->failed,
            ]));
    }

    /**
     * @return array<string, string>
     */
    private function flashForServiceOutcome(string $outcome): array
    {
        return match ($outcome) {
            'polled' => ['status' => __('Service synced successfully with the external provider.')],
            'resolved' => ['status' => __('Service sync completed and resolved divergences.')],
            'diverged' => ['status' => __('Service sync completed with unresolved divergences.')],
            'failed' => ['action' => __('Service sync failed while polling the external provider.')],
            default => ['status' => __('Service sync was skipped for this resource.')],
        };
    }
}
