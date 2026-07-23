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
        $appName = (string) config('corepanel.name', config('app.name'));

        return (new MailMessage)
            ->subject(__('New support ticket :number', ['number' => $number]))
            ->markdown('mail.tickets.opened', [
                'number' => $number,
                'subject' => $this->ticket->subject,
                'clientName' => $clientName,
                'url' => url(route('admin.tickets.show', $this->ticket)),
                'appName' => $appName,
            ]);
    }
}
