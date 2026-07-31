<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientMemberRequest;
use App\Http\Requests\Admin\UpdateClientMemberRequest;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Core\Clients\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class ClientMemberController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
    ) {
    }

    public function store(StoreClientMemberRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        try {
            $this->clientService->addMember($client, $request->membershipData());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['membership' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Member added successfully.'));
    }

    public function update(
        UpdateClientMemberRequest $request,
        Client $client,
        ClientUser $membership,
    ): RedirectResponse {
        Gate::authorize('update', $client);
        $this->assertMembershipBelongsToClient($client, $membership);

        try {
            $this->clientService->updateMember($membership, $request->membershipData($membership));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['membership' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Member updated successfully.'));
    }

    public function destroy(Client $client, ClientUser $membership): RedirectResponse
    {
        Gate::authorize('update', $client);
        $this->assertMembershipBelongsToClient($client, $membership);

        try {
            $this->clientService->removeMember($client, $membership);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['membership' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Member removed successfully.'));
    }

    private function assertMembershipBelongsToClient(Client $client, ClientUser $membership): void
    {
        if ($membership->client_id !== $client->id) {
            abort(404);
        }
    }
}
