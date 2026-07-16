<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientNoteRequest;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientNote;
use Core\Clients\Services\ClientNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ClientNoteController extends Controller
{
    public function __construct(
        private readonly ClientNoteService $clientNoteService,
    ) {
    }

    public function store(StoreClientNoteRequest $request, Client $client): RedirectResponse
    {
        /** @var User $author */
        $author = $request->user();

        try {
            $this->clientNoteService->create($client, $author, $request->body());
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['note' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Note added successfully.'));
    }

    public function destroy(Client $client, ClientNote $note): RedirectResponse
    {
        Gate::authorize('update', $client);

        /** @var User|null $actor */
        $actor = request()->user();

        if ($actor === null) {
            return redirect()->route('login');
        }

        try {
            $this->clientNoteService->delete($client, $note, $actor);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['note' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('status', __('Note deleted successfully.'));
    }
}
