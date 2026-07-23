<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientTicketRequest;
use App\Http\Requests\Client\StoreClientTicketRequest;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Tickets\DataTransferObjects\TicketData;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
    ) {
    }

    public function index(IndexClientTicketRequest $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null) {
            return view('client.tickets.index', [
                'tickets' => null,
                'filters' => $request->filters(),
                'statuses' => TicketStatus::cases(),
                'priorities' => TicketPriority::cases(),
                'clientMissing' => true,
            ]);
        }

        $filters = $request->filters();

        return view('client.tickets.index', [
            'tickets' => $this->tickets->paginateForClient($client, $filters),
            'filters' => $filters,
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'clientMissing' => false,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->can('client.tickets.create') ?? false, 403);

        $client = $this->resolveClient($request);

        if ($client === null) {
            return view('client.tickets.create', [
                'clientMissing' => true,
                'categories' => collect(),
                'services' => collect(),
                'orders' => collect(),
                'invoices' => collect(),
                'priorities' => TicketPriority::cases(),
            ]);
        }

        return view('client.tickets.create', [
            'clientMissing' => false,
            'categories' => $this->tickets->activeCategories(),
            'services' => $this->tickets->linkableServices($client),
            'orders' => $this->tickets->linkableOrders($client),
            'invoices' => $this->tickets->linkableInvoices($client),
            'priorities' => TicketPriority::cases(),
        ]);
    }

    public function store(StoreClientTicketRequest $request): RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null) {
            return redirect()
                ->route('client.tickets.create')
                ->withErrors(['ticket' => __('No client account is linked to your user.')]);
        }

        /** @var User $author */
        $author = $request->user();
        $payload = $request->payload();

        try {
            $ticket = $this->tickets->create($client, $author, TicketData::fromArray($payload));
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['ticket' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.tickets.show', $ticket)
            ->with('status', __('Ticket opened successfully.'));
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorizeTicket($request, $ticket, 'client.tickets.view');

        $ticket->load(['category', 'service.product', 'order', 'invoice', 'messages.author']);

        return view('client.tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    private function authorizeTicket(Request $request, Ticket $ticket, string $permission): void
    {
        abort_unless($request->user()?->can($permission) ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $ticket->client_id === $client->id,
            404,
        );
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
