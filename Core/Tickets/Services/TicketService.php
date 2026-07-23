<?php

namespace Core\Tickets\Services;

use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Tickets\DataTransferObjects\TicketData;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketCategory;
use Core\Tickets\Models\TicketMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Support ticket lifecycle: create, reply, close, reopen, assign, priority.
 */
class TicketService
{
    public function __construct(
        private readonly TicketNumberService $ticketNumbers,
        private readonly TicketAttachmentService $attachments,
    ) {
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: TicketStatus|null,
     *     priority?: TicketPriority|null,
     *     assigned_to?: int|null|'unassigned',
     *     sort?: string,
     *     dir?: string
     * }  $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $assignedTo = $filters['assigned_to'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'ticket_number',
            'subject',
            'status',
            'priority',
            'created_at',
            'updated_at',
        ], true)) {
            $sort = 'created_at';
        }

        $query = Ticket::query()->with(['client', 'category', 'assignee']);

        if ($status instanceof TicketStatus) {
            $query->where('status', $status->value);
        }

        if ($priority instanceof TicketPriority) {
            $query->where('priority', $priority->value);
        }

        if ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif (is_int($assignedTo)) {
            $query->where('assigned_to', $assignedTo);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('ticket_number', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhereHas('client', function ($clientQuery) use ($term): void {
                        $clientQuery->where('company_name', 'like', $term);
                    });

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Staff users that can be assigned to tickets.
     *
     * @return EloquentCollection<int, User>
     */
    public function assignableStaff(): EloquentCollection
    {
        return User::query()
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', ['super-admin', 'admin', 'support']);
            })
            ->orderBy('name')
            ->get();
    }

    public function create(Client $client, User $author, TicketData $data): Ticket
    {
        $this->assertSubject($data->subject);
        $this->assertMessage($data->message);
        $this->assertFiles($data->files);
        $this->assertCategoryExists($data->categoryId);

        return DB::transaction(function () use ($client, $author, $data): Ticket {
            $ticket = Ticket::query()->create([
                'client_id' => $client->id,
                'category_id' => $data->categoryId,
                'subject' => $data->subject,
                'status' => TicketStatus::Open,
                'priority' => $data->priority,
                'assigned_to' => null,
            ]);

            $storedAttachments = null;

            try {
                if ($data->files !== null && $data->files !== []) {
                    $storedAttachments = $this->attachments->storeMany($ticket, $data->files);
                }

                $this->storeMessage($ticket, $author, $data->message, $storedAttachments);
            } catch (\Throwable $exception) {
                if ($storedAttachments !== null) {
                    $this->attachments->deleteStored($storedAttachments);
                }

                throw $exception;
            }

            $ticket = $this->ticketNumbers->assignNumber($ticket);

            return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
        });
    }

    /**
     * @param  list<UploadedFile>|null  $files
     */
    public function reply(Ticket $ticket, User $author, string $message, ?array $files = null): TicketMessage
    {
        if ($ticket->status->isClosed()) {
            throw new RuntimeException('Closed tickets cannot receive replies. Reopen the ticket first.');
        }

        $message = trim($message);
        $this->assertMessage($message);
        $this->assertFiles($files);

        return DB::transaction(function () use ($ticket, $author, $message, $files): TicketMessage {
            $storedAttachments = null;

            try {
                if ($files !== null && $files !== []) {
                    $storedAttachments = $this->attachments->storeMany($ticket, $files);
                }

                $entry = $this->storeMessage($ticket, $author, $message, $storedAttachments);
            } catch (\Throwable $exception) {
                if ($storedAttachments !== null) {
                    $this->attachments->deleteStored($storedAttachments);
                }

                throw $exception;
            }

            $nextStatus = $this->isClientParticipant($ticket, $author)
                ? TicketStatus::Open
                : TicketStatus::Answered;

            if ($ticket->status !== $nextStatus) {
                $ticket->forceFill(['status' => $nextStatus])->save();
            }

            return $entry->load('author');
        });
    }

    public function close(Ticket $ticket): Ticket
    {
        if ($ticket->status === TicketStatus::Closed) {
            return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
        }

        $ticket->forceFill(['status' => TicketStatus::Closed])->save();

        return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
    }

    public function reopen(Ticket $ticket): Ticket
    {
        if ($ticket->status === TicketStatus::Open) {
            return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
        }

        if (! $ticket->status->isClosed()) {
            throw new RuntimeException('Only closed tickets can be reopened.');
        }

        $ticket->forceFill(['status' => TicketStatus::Open])->save();

        return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
    }

    public function assign(Ticket $ticket, ?User $assignee): Ticket
    {
        if ($ticket->status->isClosed()) {
            throw new RuntimeException('Closed tickets cannot be assigned. Reopen the ticket first.');
        }

        $attributes = [
            'assigned_to' => $assignee?->id,
        ];

        if ($assignee !== null && $ticket->status === TicketStatus::Open) {
            $attributes['status'] = TicketStatus::InProgress;
        }

        $ticket->forceFill($attributes)->save();

        return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
    }

    public function setPriority(Ticket $ticket, TicketPriority $priority): Ticket
    {
        if ($ticket->priority === $priority) {
            return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
        }

        $ticket->forceFill(['priority' => $priority])->save();

        return $ticket->fresh(['client', 'category', 'assignee', 'messages.author']) ?? $ticket;
    }

    /**
     * @param  list<array<string, mixed>>|null  $attachments
     */
    private function storeMessage(
        Ticket $ticket,
        User $author,
        string $message,
        ?array $attachments,
    ): TicketMessage {
        return TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'message' => $message,
            'attachments' => $attachments === [] ? null : $attachments,
            'created_at' => now(),
        ]);
    }

    private function isClientParticipant(Ticket $ticket, User $user): bool
    {
        $ticket->loadMissing('client');

        $client = $ticket->client;

        if ($client === null) {
            return false;
        }

        if ($client->user_id === $user->id) {
            return true;
        }

        return $client->users()->where('users.id', $user->id)->exists();
    }

    private function assertSubject(string $subject): void
    {
        if (trim($subject) === '') {
            throw new InvalidArgumentException('The ticket subject cannot be empty.');
        }
    }

    private function assertMessage(string $message): void
    {
        if (trim($message) === '') {
            throw new InvalidArgumentException('The ticket message cannot be empty.');
        }
    }

    /**
     * @param  list<UploadedFile>|null  $files
     */
    private function assertFiles(?array $files): void
    {
        if ($files === null) {
            return;
        }

        if (! array_is_list($files)) {
            throw new InvalidArgumentException('Ticket attachment files must be a list.');
        }
    }

    private function assertCategoryExists(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $exists = TicketCategory::query()->whereKey($categoryId)->exists();

        if (! $exists) {
            throw new InvalidArgumentException("Ticket category [{$categoryId}] does not exist.");
        }
    }
}
