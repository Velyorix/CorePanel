<?php

namespace App\Http\Controllers;

use Core\Clients\Models\Client;
use Core\Clients\Services\ClientImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LeaveImpersonationController extends Controller
{
    public function __construct(
        private readonly ClientImpersonationService $clientImpersonationService,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $clientId = $this->clientImpersonationService->impersonatedClientId($request);

        try {
            $this->clientImpersonationService->leave($request);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('client.dashboard')
                ->withErrors(['impersonation' => $exception->getMessage()]);
        }

        if ($clientId !== null && Client::query()->whereKey($clientId)->exists()) {
            return redirect()
                ->route('admin.clients.show', $clientId)
                ->with('status', __('Impersonation ended. You are back in the admin panel.'));
        }

        return redirect()
            ->route('admin.clients.index')
            ->with('status', __('Impersonation ended. You are back in the admin panel.'));
    }
}
