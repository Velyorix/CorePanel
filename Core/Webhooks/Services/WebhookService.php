<?php

namespace Core\Webhooks\Services;

use Core\Auth\Models\User;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Models\Webhook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WebhookService
{
    /**
     * @param  array{
     *     name: string,
     *     url: string,
     *     events: list<string>,
     *     is_active?: bool
     * }  $data
     * @return array{webhook: Webhook, secret: string}
     */
    public function create(User $user, array $data): array
    {
        $events = $this->normalizeEvents($data['events']);
        $secret = $this->generateSecret();

        $webhook = Webhook::query()->create([
            'user_id' => $user->id,
            'name' => trim($data['name']),
            'url' => trim($data['url']),
            'secret' => $secret,
            'events' => $events,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return [
            'webhook' => $webhook,
            'secret' => $secret,
        ];
    }

    /**
     * @param  array{
     *     name?: string,
     *     url?: string,
     *     events?: list<string>,
     *     is_active?: bool
     * }  $data
     */
    public function update(Webhook $webhook, array $data): Webhook
    {
        $attributes = [];

        if (array_key_exists('name', $data)) {
            $attributes['name'] = trim((string) $data['name']);
        }

        if (array_key_exists('url', $data)) {
            $attributes['url'] = trim((string) $data['url']);
        }

        if (array_key_exists('events', $data)) {
            $attributes['events'] = $this->normalizeEvents($data['events']);
        }

        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
        }

        if ($attributes !== []) {
            $webhook->forceFill($attributes)->save();
        }

        return $webhook->fresh() ?? $webhook;
    }

    /**
     * @return array{webhook: Webhook, secret: string}
     */
    public function rotateSecret(Webhook $webhook): array
    {
        $secret = $this->generateSecret();
        $webhook->forceFill(['secret' => $secret])->save();

        return [
            'webhook' => $webhook->fresh() ?? $webhook,
            'secret' => $secret,
        ];
    }

    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Webhook>
     */
    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Webhook::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUser(User $user, int $id): ?Webhook
    {
        return Webhook::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  list<string>  $events
     * @return list<string>
     */
    private function normalizeEvents(array $events): array
    {
        $normalized = [];

        foreach ($events as $event) {
            $value = trim((string) $event);

            if ($value === '') {
                continue;
            }

            if ($value === '*') {
                return ['*'];
            }

            if (! in_array($value, WebhookEvent::values(), true)) {
                throw new InvalidArgumentException("Unsupported webhook event [{$value}].");
            }

            $normalized[] = $value;
        }

        $normalized = array_values(array_unique($normalized));

        if ($normalized === []) {
            throw new InvalidArgumentException('At least one webhook event is required.');
        }

        return $normalized;
    }

    private function generateSecret(): string
    {
        $prefix = (string) config('corepanel.api.webhooks.secret_prefix', 'cwhsec_');

        return $prefix.Str::random(40);
    }
}
