<?php

namespace Database\Factories;

use Core\Webhooks\Enums\WebhookDeliveryStatus;
use Core\Webhooks\Enums\WebhookEvent;
use Core\Webhooks\Models\Webhook;
use Core\Webhooks\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = WebhookEvent::InvoicePaid->value;

        return [
            'webhook_id' => Webhook::factory(),
            'delivery_id' => (string) Str::uuid(),
            'event' => $event,
            'payload' => [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => ['invoice_id' => 1],
            ],
            'status' => WebhookDeliveryStatus::Pending,
            'attempt' => 0,
            'http_status' => null,
            'response_body' => null,
            'error_message' => null,
            'next_retry_at' => null,
            'delivered_at' => null,
        ];
    }
}
