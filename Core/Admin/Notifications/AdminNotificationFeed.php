<?php

namespace Core\Admin\Notifications;

class AdminNotificationFeed
{
    /**
     * Placeholder notifications shown until the notification system is wired.
     *
     * @return list<array{key: string, title: string, message: string, time: string, unread: bool, variant: string}>
     */
    public function placeholders(): array
    {
        return [
            [
                'key' => 'node_cpu',
                'title' => __('High CPU usage detected'),
                'message' => __('Node Atlas-01 reported sustained load above 85%.'),
                'time' => __('5 min ago'),
                'unread' => true,
                'variant' => 'warning',
            ],
            [
                'key' => 'payment_webhook',
                'title' => __('Payment webhook failed'),
                'message' => __('A billing webhook retry is pending manual review.'),
                'time' => __('18 min ago'),
                'unread' => true,
                'variant' => 'danger',
            ],
            [
                'key' => 'support_ticket',
                'title' => __('New support ticket'),
                'message' => __('Ticket #1042 was opened by Acme Corp.'),
                'time' => __('1 hr ago'),
                'unread' => false,
                'variant' => 'primary',
            ],
        ];
    }

    public function unreadCount(): int
    {
        return collect($this->placeholders())
            ->where('unread', true)
            ->count();
    }
}
