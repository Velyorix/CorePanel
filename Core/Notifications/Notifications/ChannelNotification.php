<?php

namespace Core\Notifications\Notifications;

use Core\Notifications\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Simple multi-channel notification (mail + database by default).
 */
class ChannelNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>|null  $channels
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly array $meta = [],
        private readonly ?array $channels = null,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>|null  $channels
     */
    public static function make(
        string $title,
        string $message,
        array $meta = [],
        ?array $channels = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): self {
        return new self($title, $message, $meta, $channels, $actionUrl, $actionLabel);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($this->channels !== null && $this->channels !== []) {
            return array_values($this->channels);
        }

        return app(NotificationService::class)->defaultChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->message);

        if ($this->actionUrl !== null && $this->actionUrl !== '') {
            $mail->action(
                $this->actionLabel ?: __('View details'),
                $this->actionUrl,
            );
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'meta' => $this->meta,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
        ];
    }
}
