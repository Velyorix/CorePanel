<?php

namespace Core\Webhooks\Services;

use Core\Webhooks\Enums\WebhookDeliveryStatus;
use Core\Webhooks\Models\WebhookDelivery;
use Core\Webhooks\Support\WebhookSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookDeliveryService
{
    /**
     * Attempt a single HTTP delivery. Returns true when accepted by the receiver.
     */
    public function attempt(WebhookDelivery $delivery): bool
    {
        $webhook = $delivery->webhook;

        if ($webhook === null || ! $webhook->is_active) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'error_message' => 'Webhook endpoint is inactive or missing.',
                'next_retry_at' => null,
            ])->save();

            return false;
        }

        $rawBody = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = WebhookSigner::sign((string) $webhook->secret, $timestamp, $rawBody);

        $timeout = max(1, (int) config('corepanel.api.webhooks.timeout_seconds', 10));
        $signatureHeader = (string) config('corepanel.api.webhooks.signature_header', 'X-CorePanel-Signature');
        $timestampHeader = (string) config('corepanel.api.webhooks.timestamp_header', 'X-CorePanel-Timestamp');
        $eventHeader = (string) config('corepanel.api.webhooks.event_header', 'X-CorePanel-Event');
        $deliveryHeader = (string) config('corepanel.api.webhooks.delivery_header', 'X-CorePanel-Delivery');

        $attempt = (int) $delivery->attempt + 1;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CorePanel-Webhooks/1.0',
                    $signatureHeader => $signature,
                    $timestampHeader => $timestamp,
                    $eventHeader => $delivery->event,
                    $deliveryHeader => $delivery->delivery_id,
                ])
                ->withBody($rawBody, 'application/json')
                ->post($webhook->url);

            $status = $response->status();
            $body = $this->truncate((string) $response->body());

            if ($status >= 200 && $status < 300) {
                $delivery->forceFill([
                    'attempt' => $attempt,
                    'status' => WebhookDeliveryStatus::Delivered,
                    'http_status' => $status,
                    'response_body' => $body,
                    'error_message' => null,
                    'next_retry_at' => null,
                    'delivered_at' => now(),
                ])->save();

                $webhook->forceFill(['last_delivery_at' => now()])->save();

                return true;
            }

            $delivery->forceFill([
                'attempt' => $attempt,
                'http_status' => $status,
                'response_body' => $body,
                'error_message' => "Receiver returned HTTP {$status}.",
            ])->save();

            return false;
        } catch (ConnectionException $exception) {
            $delivery->forceFill([
                'attempt' => $attempt,
                'http_status' => null,
                'response_body' => null,
                'error_message' => $this->truncate($exception->getMessage()),
            ])->save();

            return false;
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'attempt' => $attempt,
                'http_status' => null,
                'response_body' => null,
                'error_message' => $this->truncate($exception->getMessage()),
            ])->save();

            return false;
        }
    }

    public function scheduleRetry(WebhookDelivery $delivery): void
    {
        $maxAttempts = max(1, (int) config('corepanel.api.webhooks.max_attempts', 5));
        $attempt = (int) $delivery->attempt;

        if ($attempt >= $maxAttempts) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $backoff = config('corepanel.api.webhooks.backoff_seconds', [60, 300, 900, 3600, 21600]);
        $seconds = is_array($backoff)
            ? (int) ($backoff[min($attempt - 1, count($backoff) - 1)] ?? 60)
            : 60;

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Retrying,
            'next_retry_at' => now()->addSeconds(max(1, $seconds)),
        ])->save();
    }

    private function truncate(string $value): string
    {
        $max = max(100, (int) config('corepanel.api.webhooks.response_body_max_bytes', 2048));

        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max).'…';
    }
}
