<?php

namespace Core\Clients\Notifications;

use Core\Clients\Models\ClientUserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientUserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ClientUserInvitation $invitation,
        public readonly string $plainToken,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invitation->loadMissing('client');

        $clientName = $this->invitation->client?->company_name ?: 'your client account';

        $url = url(route('client.invitations.accept', [
            'token' => $this->plainToken,
        ]));

        return (new MailMessage)
            ->subject(__('You are invited to join :client', ['client' => $clientName]))
            ->line(__('Someone invited you to join their client account in CorePanel.'))
            ->line(__('Role: :role', ['role' => ucfirst($this->invitation->role->value)]))
            ->action(__('Accept invitation'), $url)
            ->line(__('This invitation will expire soon if not accepted.'));
    }
}

