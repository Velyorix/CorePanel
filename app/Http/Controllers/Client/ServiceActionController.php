<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Services\Enums\ServiceAction;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        abort_unless($request->user()?->can('client.services.manage') ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $service->client_id === $client->id,
            404,
        );

        $actor = $request->user();
        $performedBy = $actor instanceof User ? $actor : null;

        try {
            $this->control->execute($service, $action, $performedBy);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.services.show', $service)
            ->with('status', $successMessage);
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
