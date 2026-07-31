<?php

namespace Core\Admin\Notifications;

use Core\Admin\Services\AdminNotificationService;
use Illuminate\Support\Carbon;

class AdminNotificationFeed
{
    public function __construct(
        private readonly AdminNotificationService $notifications,
    ) {
    }

    /**
     * @return list<array{key: string, title: string, message: string, time: string, unread: bool, variant: string}>
     */
    public function recent(int $limit = 20): array
    {
        return $this->notifications->recent($limit)
            ->map(fn ($notification): array => [
                'key' => $notification->dedupe_key,
                'title' => $notification->title,
                'message' => $notification->message,
                'time' => $this->formatTime($notification->created_at),
                'unread' => $notification->isUnread(),
                'variant' => $notification->variant,
            ])
            ->all();
    }

    public function unreadCount(): int
    {
        return $this->notifications->unreadCount();
    }

    private function formatTime(?Carbon $createdAt): string
    {
        if ($createdAt === null) {
            return __('just now');
        }

        return $createdAt->diffForHumans();
    }
}
