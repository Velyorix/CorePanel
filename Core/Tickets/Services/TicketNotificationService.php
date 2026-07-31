<?php

namespace Core\Tickets\Services;

use App\Models\User;
use Core\Auth\Models\User as AuthUser;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Core\Tickets\Notifications\TicketOpenedNotification;
use Core\Tickets\Notifications\TicketReplyNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Mail notifications for ticket open and reply events.
 */
class TicketNotificationService
{
    public function enabled(): bool
    {
        return (bool) config('corepanel.tickets.notifications.enabled', true);
    }

    public function notifyOpened(Ticket $ticket): void
    {
        if (! $this->enabled()) {
            return;
        }

        $recipients = $this->staffRecipients($ticket);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TicketOpenedNotification($ticket));
    }

    public function notifyReplied(Ticket $ticket, TicketMessage $message, AuthUser $author): void
    {
        if (! $this->enabled()) {
            return;
        }

        $authorModel = User::query()->find($author->id);

        if (! $authorModel instanceof User) {
            return;
        }

        if ($this->isClientParticipant($ticket, $authorModel)) {
            $recipients = $this->staffRecipients($ticket);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send(
                $recipients,
                new TicketReplyNotification($ticket, $message, $authorModel, forStaff: true),
            );

            return;
        }

        $recipients = $this->clientRecipients($ticket)
            ->reject(fn (User $user): bool => $user->id === $authorModel->id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new TicketReplyNotification($ticket, $message, $authorModel, forStaff: false),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function staffRecipients(Ticket $ticket): Collection
    {
        if ($ticket->assigned_to !== null) {
            $assignee = User::query()->find($ticket->assigned_to);

            if ($assignee instanceof User) {
                return collect([$assignee]);
            }
        }

        return User::query()
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', ['super-admin', 'admin', 'support']);
            })
            ->orderBy('name')
            ->get()
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function clientRecipients(Ticket $ticket): Collection
    {
        $ticket->loadMissing(['client.users']);

        $client = $ticket->client;

        if ($client === null) {
            return collect();
        }

        $ids = collect();

        if ($client->user_id !== null) {
            $ids->push($client->user_id);
        }

        foreach ($client->users as $member) {
            $ids->push($member->id);
        }

        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->values();
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
}
