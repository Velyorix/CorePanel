<?php

namespace Core\Webhooks\Jobs;

use Core\Automation\Concerns\IdempotentUniqueJob;
use Core\Automation\Services\AutomationIdempotencyKey;
use Core\Webhooks\Enums\WebhookDeliveryStatus;
use Core\Webhooks\Models\WebhookDelivery;
use Core\Webhooks\Services\WebhookDeliveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use IdempotentUniqueJob;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $deliveryId,
    ) {
        $this->configureUniqueFor();
    }

    public function idempotencyKey(): string
    {
        return app(AutomationIdempotencyKey::class)->forJob(self::class, [
            'delivery_id' => $this->deliveryId,
        ]);
    }

    public function handle(WebhookDeliveryService $deliveries): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        if (in_array($delivery->status, [WebhookDeliveryStatus::Delivered, WebhookDeliveryStatus::Failed], true)) {
            return;
        }

        if ($deliveries->attempt($delivery)) {
            return;
        }

        $delivery->refresh();
        $deliveries->scheduleRetry($delivery);
        $delivery->refresh();

        if ($delivery->status === WebhookDeliveryStatus::Retrying && $delivery->next_retry_at !== null) {
            self::dispatch($delivery->id)->delay($delivery->next_retry_at);
        }
    }
}
