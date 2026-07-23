<?php

namespace Core\Tickets\Notifications;

use Core\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketOpenedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
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
        $this->ticket->loadMissing('client');

        $number = $this->ticket->ticket_number ?: ('#'.$this->ticket->id);
        $clientName = $this->ticket->client?->company_name ?: __('Client #:id', ['id' => $this->ticket->client_id]);

        return (new MailMessage)
            ->subject(__('New support ticket :number', ['number' => $number]))
            ->line(__('A new support ticket has been opened.'))
            ->line(__('Ticket: :number', ['number' => $number]))
            ->line(__('Subject: :subject', ['subject' => $this->ticket->subject]))
            ->line(__('Client: :client', ['client' => $clientName]))
            ->action(__('View ticket'), url(route('admin.tickets.show', $this->ticket)));
    }
}
