<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Core\Clients\Services\ClientInvitationService;
use Core\Auth\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class ClientInvitationAcceptController extends Controller
{
    public function __construct(
        private readonly ClientInvitationService $clientInvitationService,
    ) {
    }

    public function accept(string $token, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('Please sign in to accept invitations.')]);
        }

        try {
            $this->clientInvitationService->acceptInvitation($token, $user);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return redirect()
                ->route('client.dashboard')
                ->withErrors(['email' => $exception->getMessage()]);
        }

        return redirect()->route('client.dashboard');
    }
}

