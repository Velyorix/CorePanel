<?php

namespace Core\Notifications\Services;

use Core\Auth\Models\User as AuthUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user opt-in/opt-out for notification channels and categories.
 * Auth/security mail is never filtered.
 */
class NotificationPreferenceService
{
    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_DATABASE = 'database';

    /**
     * @var list<string>
     */
    public const CHANNELS = [self::CHANNEL_MAIL, self::CHANNEL_DATABASE];

    /**
     * @var list<string>
     */
    public const CATEGORIES = ['billing', 'tickets', 'services'];

    /**
     * @return array{
     *     channels: array<string, bool>,
     *     categories: array<string, array<string, bool>>
     * }
     */
    public function defaults(): array
    {
        $channels = [];
        foreach (self::CHANNELS as $channel) {
            $channels[$channel] = true;
        }

        $categories = [];
        foreach (self::CATEGORIES as $category) {
            $categories[$category] = $channels;
        }

        return [
            'channels' => $channels,
            'categories' => $categories,
        ];
    }

    /**
     * @return array{
     *     channels: array<string, bool>,
     *     categories: array<string, array<string, bool>>
     * }
     */
    public function forUser(Authenticatable|Model $user): array
    {
        $defaults = $this->defaults();
        $stored = $user->getAttribute('notification_preferences');

        if (! is_array($stored)) {
            return $defaults;
        }

        $channels = $defaults['channels'];
        foreach (self::CHANNELS as $channel) {
            if (array_key_exists($channel, $stored['channels'] ?? [])) {
                $channels[$channel] = (bool) $stored['channels'][$channel];
            }
        }

        $categories = $defaults['categories'];
        foreach (self::CATEGORIES as $category) {
            foreach (self::CHANNELS as $channel) {
                if (array_key_exists($channel, $stored['categories'][$category] ?? [])) {
                    $categories[$category][$channel] = (bool) $stored['categories'][$category][$channel];
                }
            }
        }

        return [
            'channels' => $channels,
            'categories' => $categories,
        ];
    }

    /**
     * @param  array{
     *     channels?: array<string, mixed>,
     *     categories?: array<string, array<string, mixed>>
     * }  $input
     */
    public function update(Authenticatable|Model $user, array $input): void
    {
        $defaults = $this->defaults();

        $channels = [];
        foreach (self::CHANNELS as $channel) {
            $channels[$channel] = array_key_exists($channel, $input['channels'] ?? [])
                ? (bool) $input['channels'][$channel]
                : false;
        }

        $categories = [];
        foreach (self::CATEGORIES as $category) {
            $categories[$category] = [];
            foreach (self::CHANNELS as $channel) {
                $categories[$category][$channel] = array_key_exists(
                    $channel,
                    $input['categories'][$category] ?? [],
                )
                    ? (bool) $input['categories'][$category][$channel]
                    : false;
            }
        }

        $user->forceFill([
            'notification_preferences' => [
                'channels' => $channels ?: $defaults['channels'],
                'categories' => $categories ?: $defaults['categories'],
            ],
        ])->save();
    }

    public function allows(object $notifiable, string $channel, ?string $category = null): bool
    {
        if (! $notifiable instanceof Model && ! $notifiable instanceof Authenticatable) {
            return true;
        }

        if (! $notifiable instanceof AuthUser && ! method_exists($notifiable, 'getAttribute')) {
            return true;
        }

        if (! in_array($channel, self::CHANNELS, true)) {
            return true;
        }

        $prefs = $this->forUser($notifiable);

        if (! ($prefs['channels'][$channel] ?? true)) {
            return false;
        }

        if ($category === null || ! in_array($category, self::CATEGORIES, true)) {
            return true;
        }

        return (bool) ($prefs['categories'][$category][$channel] ?? true);
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    public function filterChannels(object $notifiable, array $channels, ?string $category = null): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $channel): string => trim((string) $channel),
            $channels,
        )));

        return array_values(array_filter(
            $normalized,
            fn (string $channel): bool => $this->allows($notifiable, $channel, $category),
        ));
    }
}
