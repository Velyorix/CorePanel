<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientInvitationRequest;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientInvitationService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use RuntimeException;

class ClientInvitationController extends Controller
{
    public function __construct(
        private readonly ClientInvitationService $clientInvitationService,
    ) {
    }

    public function store(StoreClientInvitationRequest $request, Client $client): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $data = $request->invitationData();

        try {
            $this->clientInvitationService->createInvitation(
                $client,
                $data['email'],
                $data['role'],
                $data['permissions'],
                $user,
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['invitation' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['invitation' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Invitation sent successfully.'));
    }
}

