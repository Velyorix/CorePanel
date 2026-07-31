<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ClientImpersonationController extends Controller
{
    public function __construct(
        private readonly ClientImpersonationService $clientImpersonationService,
    ) {
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('impersonate', $client);

        /** @var User|null $actor */
        $actor = $request->user();

        if ($actor === null) {
            return redirect()->route('login');
        }

        try {
            $this->clientImpersonationService->start($actor, $client, $request);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['impersonation' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.dashboard')
            ->with('status', __('You are now impersonating the client owner.'));
    }
}
