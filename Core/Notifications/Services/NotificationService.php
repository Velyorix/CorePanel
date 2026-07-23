<?php

namespace Core\Notifications\Services;

use Core\Notifications\Notifications\ChannelNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use InvalidArgumentException;

/**
 * Multi-channel notification delivery (mail, database, optional queue).
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('corepanel.notifications.enabled', true);
    }

    /**
     * @return list<string>
     */
    public function defaultChannels(): array
    {
        $channels = config('corepanel.notifications.default_channels', ['mail', 'database']);

        if (! is_array($channels)) {
            return ['mail', 'database'];
        }

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $channel): string => trim((string) $channel),
            $channels,
        )));

        return $normalized === [] ? ['mail', 'database'] : $normalized;
    }

    public function queueByDefault(): bool
    {
        return (bool) config('corepanel.notifications.queue_by_default', false);
    }

    /**
     * @param  mixed  $notifiables
     * @param  list<string>|null  $channels
     */
    public function send(
        mixed $notifiables,
        Notification $notification,
        ?array $channels = null,
        ?bool $queue = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $channels = $this->normalizeChannels($channels);
        $shouldQueue = $queue ?? $this->queueByDefault();

        if ($shouldQueue) {
            if ($channels !== null) {
                throw new InvalidArgumentException(
                    'Queued notifications cannot override channels; set channels on the notification via() method instead.',
                );
            }

            if (! $notification instanceof ShouldQueue) {
                throw new InvalidArgumentException(
                    'Queued delivery requires a notification that implements ShouldQueue.',
                );
            }

            NotificationFacade::send($notifiables, $notification);

            return;
        }

        if ($channels === null) {
            NotificationFacade::sendNow($notifiables, $notification);

            return;
        }

        $category = $notification instanceof ChannelNotification
            ? $notification->category
            : null;

        foreach ($this->normalizeNotifiables($notifiables) as $notifiable) {
            $filtered = $this->preferences->filterChannels($notifiable, $channels, $category);

            if ($filtered === []) {
                continue;
            }

            NotificationFacade::sendNow($notifiable, $notification, $filtered);
        }
    }

    /**
     * @param  mixed  $notifiables
     * @param  list<string>|null  $channels
     */
    public function queue(
        mixed $notifiables,
        Notification $notification,
        ?array $channels = null,
    ): void {
        $this->send($notifiables, $notification, $channels, queue: true);
    }

    /**
     * @param  list<string>|null  $channels
     */
    public function sendMail(
        string $email,
        Notification $notification,
        ?array $channels = null,
        ?bool $queue = null,
    ): void {
        $this->send(
            NotificationFacade::route('mail', $email),
            $notification,
            $channels ?? ['mail'],
            $queue,
        );
    }

    /**
     * @param  list<string>|null  $channels
     * @return list<string>|null
     */
    private function normalizeChannels(?array $channels): ?array
    {
        if ($channels === null) {
            return null;
        }

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $channel): string => trim((string) $channel),
            $channels,
        )));

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return Collection<int, mixed>
     */
    private function normalizeNotifiables(mixed $notifiables): Collection
    {
        if ($notifiables instanceof Collection) {
            return $notifiables->values();
        }

        if (is_array($notifiables)) {
            return collect($notifiables)->values();
        }

        return collect([$notifiables]);
    }
}
