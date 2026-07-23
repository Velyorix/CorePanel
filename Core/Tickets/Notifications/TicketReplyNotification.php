<?php

namespace Core\Tickets\Notifications;

use Core\Auth\Models\User;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketMessage $message,
        public readonly User $author,
        public readonly bool $forStaff = false,
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
        $number = $this->ticket->ticket_number ?: ('#'.$this->ticket->id);
        $url = $this->forStaff
            ? url(route('admin.tickets.show', $this->ticket))
            : url(route('client.tickets.show', $this->ticket));

        $excerpt = mb_strlen($this->message->message) > 200
            ? mb_substr($this->message->message, 0, 200).'…'
            : $this->message->message;

        return (new MailMessage)
            ->subject(__('New reply on ticket :number', ['number' => $number]))
            ->line(__('There is a new reply on support ticket :number.', ['number' => $number]))
            ->line(__('Subject: :subject', ['subject' => $this->ticket->subject]))
            ->line(__('From: :name', ['name' => $this->author->name]))
            ->line($excerpt)
            ->action(__('View ticket'), $url);
    }
}
