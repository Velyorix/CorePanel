<?php

namespace Core\Admin\Services;

use Core\Admin\Models\AdminNotification;
use Illuminate\Database\Eloquent\Collection;

class AdminNotificationService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function upsert(
        string $type,
        string $dedupeKey,
        string $title,
        string $message,
        string $variant = 'warning',
        ?array $metadata = null,
    ): AdminNotification {
        return AdminNotification::query()->updateOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'variant' => $variant,
                'metadata' => $metadata,
                'read_at' => null,
            ],
        );
    }

    public function markReadByDedupeKey(string $dedupeKey): void
    {
        AdminNotification::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return Collection<int, AdminNotification>
     */
    public function recent(int $limit = 20): Collection
    {
        return AdminNotification::query()
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get();
    }

    public function unreadCount(): int
    {
        return AdminNotification::query()
            ->whereNull('read_at')
            ->count();
    }
}
