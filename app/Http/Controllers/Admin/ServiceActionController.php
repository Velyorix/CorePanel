<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Auth\Models\User;
use Core\Services\Enums\ServiceAction;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ServiceActionController extends Controller
{
    public function __construct(
        private readonly ServiceControlService $control,
    ) {
    }

    public function start(Request $request, Service $service): RedirectResponse
    {
        return $this->run($request, $service, ServiceAction::Start, __('Service start requested.'));
    }

    public function stop(Request $request, Service $service): RedirectResponse
    {
        return $this->run($request, $service, ServiceAction::Stop, __('Service stop requested.'));
    }

    public function restart(Request $request, Service $service): RedirectResponse
    {
        return $this->run($request, $service, ServiceAction::Restart, __('Service restart requested.'));
    }

    public function suspend(Request $request, Service $service): RedirectResponse
    {
        return $this->run($request, $service, ServiceAction::Suspend, __('Service suspended successfully.'));
    }

    public function unsuspend(Request $request, Service $service): RedirectResponse
    {
        return $this->run($request, $service, ServiceAction::Unsuspend, __('Service unsuspended successfully.'));
    }

    public function terminate(Request $request, Service $service): RedirectResponse
    {
        return $this->run($request, $service, ServiceAction::Terminate, __('Service terminated successfully.'));
    }

    public function reinstall(Request $request, Service $service): RedirectResponse
    {
        return $this->run($request, $service, ServiceAction::Reinstall, __('Service reinstall requested.'));
    }

    private function run(
        Request $request,
        Service $service,
        ServiceAction $action,
        string $successMessage,
    ): RedirectResponse {
        Gate::authorize('manage', $service);

        $actor = $request->user();
        $performedBy = $actor instanceof User ? $actor : null;

        try {
            $this->control->execute($service, $action, $performedBy);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.services.show', $service)
            ->with('status', $successMessage);
    }
}
