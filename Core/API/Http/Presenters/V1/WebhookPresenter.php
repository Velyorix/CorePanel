<?php

namespace Core\API\Http\Presenters\V1;

use Core\Webhooks\Models\Webhook;
use Core\Webhooks\Models\WebhookDelivery;

final class WebhookPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function webhook(Webhook $webhook, ?string $secret = null): array
    {
        $payload = [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events ?? [],
            'is_active' => (bool) $webhook->is_active,
            'last_delivery_at' => $webhook->last_delivery_at?->toIso8601String(),
            'created_at' => $webhook->created_at?->toIso8601String(),
            'updated_at' => $webhook->updated_at?->toIso8601String(),
        ];

        if ($secret !== null) {
            $payload['secret'] = $secret;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function delivery(WebhookDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivery_id' => $delivery->delivery_id,
            'event' => $delivery->event,
            'status' => $delivery->status?->value ?? $delivery->status,
            'attempt' => $delivery->attempt,
            'http_status' => $delivery->http_status,
            'error_message' => $delivery->error_message,
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }
}
