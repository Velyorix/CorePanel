<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignTicketRequest;
use App\Http\Requests\Admin\StoreTicketReplyRequest;
use App\Http\Requests\Admin\UpdateTicketPriorityRequest;
use App\Models\User;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Services\TicketAttachmentService;
use Core\Tickets\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketActionController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketAttachmentService $attachments,
    ) {
    }

    public function reply(StoreTicketReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        /** @var User $author */
        $author = $request->user();

        try {
            $this->tickets->reply(
                $ticket,
                $author,
                $request->message(),
                $request->filesList(),
            );
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['ticket' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tickets.show', $ticket)
            ->with('status', __('Reply posted successfully.'));
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $assigneeId = $request->assigneeId();
        $assignee = $assigneeId === null
            ? null
            : User::query()->findOrFail($assigneeId);

        try {
            $this->tickets->assign($ticket, $assignee);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['ticket' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tickets.show', $ticket)
            ->with('status', $assignee === null
                ? __('Ticket unassigned successfully.')
                : __('Ticket assigned successfully.'));
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('close', $ticket);

        try {
            $this->tickets->close($ticket);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['ticket' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tickets.show', $ticket)
            ->with('status', __('Ticket closed successfully.'));
    }

    public function reopen(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('close', $ticket);

        try {
            $this->tickets->reopen($ticket);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['ticket' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tickets.show', $ticket)
            ->with('status', __('Ticket reopened successfully.'));
    }

    public function priority(UpdateTicketPriorityRequest $request, Ticket $ticket): RedirectResponse
    {
        try {
            $this->tickets->setPriority($ticket, $request->priority());
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['ticket' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.tickets.show', $ticket)
            ->with('status', __('Ticket priority updated successfully.'));
    }

    public function downloadAttachment(Ticket $ticket, string $attachmentId): StreamedResponse
    {
        Gate::authorize('view', $ticket);

        try {
            return $this->attachments->download($ticket, $attachmentId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            abort(404, $exception->getMessage());
        }
    }
}
