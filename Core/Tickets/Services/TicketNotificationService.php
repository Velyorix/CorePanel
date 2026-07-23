<?php

namespace Core\Tickets\Services;

use Core\Auth\Models\User;
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

    public function notifyReplied(Ticket $ticket, TicketMessage $message, User $author): void
    {
        if (! $this->enabled()) {
            return;
        }

        $ticket->loadMissing(['client.owner', 'assignee']);

        if ($this->isClientParticipant($ticket, $author)) {
            $recipients = $this->staffRecipients($ticket);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send(
                $recipients,
                new TicketReplyNotification($ticket, $message, $author, forStaff: true),
            );

            return;
        }

        $recipients = $this->clientRecipients($ticket)
            ->reject(fn (User $user): bool => $user->id === $author->id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new TicketReplyNotification($ticket, $message, $author, forStaff: false),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function staffRecipients(Ticket $ticket): Collection
    {
        $ticket->loadMissing('assignee');

        if ($ticket->assignee instanceof User) {
            return collect([$ticket->assignee]);
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
        $ticket->loadMissing(['client.owner', 'client.users']);

        $client = $ticket->client;

        if ($client === null) {
            return collect();
        }

        $recipients = collect();

        if ($client->owner instanceof User) {
            $recipients->push($client->owner);
        }

        foreach ($client->users as $member) {
            $recipients->push($member);
        }

        return $recipients
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
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
