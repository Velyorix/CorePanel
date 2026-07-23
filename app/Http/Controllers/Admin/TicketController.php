<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTicketRequest;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Services\TicketService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
    ) {
    }

    public function index(IndexTicketRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.tickets.index', [
            'tickets' => $this->tickets->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'assignees' => $this->tickets->assignableStaff(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'client.owner',
            'category',
            'service.product',
            'order',
            'invoice',
            'assignee',
            'messages.author',
        ]);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'priorities' => TicketPriority::cases(),
            'assignees' => $this->tickets->assignableStaff(),
        ]);
    }
}
