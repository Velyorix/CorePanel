<?php

namespace Core\Webhooks\Services;

use Core\Webhooks\Enums\WebhookDeliveryStatus;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Jobs\DeliverWebhookJob;
use Core\Webhooks\Models\Webhook;
use Core\Webhooks\Models\WebhookDelivery;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(WebhookEvent|string $event, array $data): void
    {
        if (! (bool) config('corepanel.api.webhooks.enabled', true)) {
            return;
        }

        $eventName = $event instanceof WebhookEvent ? $event->value : $event;

        if (! in_array($eventName, WebhookEvent::values(), true)) {
            return;
        }

        $payload = [
            'event' => $eventName,
            'timestamp' => now()->utc()->toIso8601String(),
            'data' => $data,
        ];

        $webhooks = Webhook::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($webhooks as $webhook) {
            if (! $webhook->listensTo($eventName)) {
                continue;
            }

            $delivery = WebhookDelivery::query()->create([
                'webhook_id' => $webhook->id,
                'delivery_id' => (string) Str::uuid(),
                'event' => $eventName,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending,
                'attempt' => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        }
    }
}
