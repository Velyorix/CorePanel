<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ClientStatusController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
    ) {
    }

    public function suspend(Request $request, Client $client): RedirectResponse
    {
        return $this->runTransition($request, $client, 'suspend', __('Client suspended successfully.'));
    }

    public function unsuspend(Request $request, Client $client): RedirectResponse
    {
        return $this->runTransition($request, $client, 'unsuspend', __('Client unsuspended successfully.'));
    }

    public function close(Request $request, Client $client): RedirectResponse
    {
        return $this->runTransition($request, $client, 'close', __('Client closed successfully.'));
    }

    public function reopen(Request $request, Client $client): RedirectResponse
    {
        return $this->runTransition($request, $client, 'reopen', __('Client reopened successfully.'));
    }

    private function runTransition(
        Request $request,
        Client $client,
        string $action,
        string $successMessage,
    ): RedirectResponse {
        Gate::authorize('update', $client);

        $reason = filled($request->input('reason'))
            ? trim((string) $request->input('reason'))
            : null;

        try {
            $this->clientService->{$action}($client, $reason);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', $successMessage);
    }
}
