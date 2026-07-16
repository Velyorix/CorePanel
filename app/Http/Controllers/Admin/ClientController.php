<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexClientRequest;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\User;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
    ) {
    }

    public function index(IndexClientRequest $request): View
    {
        $filters = $request->filters();

        $clients = $this->clientService->paginateForAdmin($filters);

        return view('admin.clients.index', [
            'clients' => $clients,
            'filters' => $filters,
            'statuses' => ClientStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('admin.clients.create', $this->formData());
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        try {
            $client = $this->clientService->create($request->clientData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['client' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Client created successfully.'));
    }

    public function show(Client $client): View
    {
        Gate::authorize('view', $client);

        $client->load(['owner', 'memberships.user', 'invitations']);

        $memberIds = $client->memberships->pluck('user_id')->all();

        $availableUsers = User::query()
            ->orderBy('email')
            ->when($memberIds !== [], fn ($query) => $query->whereKeyNot($memberIds))
            ->get(['id', 'name', 'email']);

        $pendingInvitations = $client->invitations
            ->filter(fn ($invitation) => $invitation->isPending())
            ->values();

        return view('admin.clients.show', [
            'client' => $client,
            'availableUsers' => $availableUsers,
            'membershipRoles' => ClientMembershipRole::cases(),
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        $client->load('owner');

        return view('admin.clients.edit', array_merge($this->formData(), [
            'client' => $client,
        ]));
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        try {
            $client = $this->clientService->update($client, $request->clientData());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['client' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Client updated successfully.'));
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);

        $this->clientService->delete($client);

        return redirect()
            ->route('admin.clients.index')
            ->with('status', __('Client deleted successfully.'));
    }

    /**
     * @return array{users: \Illuminate\Database\Eloquent\Collection<int, User>}
     */
    private function formData(): array
    {
        return [
            'users' => User::query()->orderBy('email')->get(['id', 'name', 'email']),
        ];
    }
}
