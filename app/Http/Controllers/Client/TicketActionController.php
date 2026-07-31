<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientTicketReplyRequest;
use App\Models\User;
use Core\Auth\Models\User as CoreUser;
use Core\Clients\Models\Client;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Services\TicketAttachmentService;
use Core\Tickets\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function reply(StoreClientTicketReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeOwnedTicket($request, $ticket);

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
            ->route('client.tickets.show', $ticket)
            ->with('status', __('Reply posted successfully.'));
    }

    public function downloadAttachment(Request $request, Ticket $ticket, string $attachmentId): StreamedResponse
    {
        abort_unless($request->user()?->can('client.tickets.view') ?? false, 403);
        $this->authorizeOwnedTicket($request, $ticket);

        try {
            return $this->attachments->download($ticket, $attachmentId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            abort(404, $exception->getMessage());
        }
    }

    private function authorizeOwnedTicket(Request $request, Ticket $ticket): void
    {
        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $ticket->client_id === $client->id,
            404,
        );
    }

    private function resolveClient(Request $request): ?Client
    {
        $user = $request->user();

        if (! $user instanceof CoreUser) {
            return null;
        }

        return $user->clients()->orderBy('clients.id')->first()
            ?? $user->ownedClients()->orderBy('id')->first();
    }
}
