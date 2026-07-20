<?php

namespace Core\Provisioning\Services;

use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Records definitive provisioning failures and supports requeue / close actions.
 */
class ProvisioningDeadLetterService
{
    public function __construct(
        private readonly ProvisioningEngine $engine,
    ) {
    }

    /**
     * Upsert an open dead letter for the service (idempotent per open entry).
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        int $serviceId,
        ?Throwable $exception = null,
        int $attempts = 1,
        array $payload = [],
    ): ProvisioningDeadLetter {
        $service = Service::query()->find($serviceId);

        $attributes = [
            'module' => $service?->module,
            'exception_class' => $exception !== null ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
            'attempts' => max(1, $attempts),
            'payload' => $payload === [] ? null : $payload,
            'failed_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ];

        $existing = ProvisioningDeadLetter::query()
            ->where('service_id', $serviceId)
            ->where('status', ProvisioningDeadLetterStatus::PendingReview)
            ->first();

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing->fresh() ?? $existing;
        }

        return ProvisioningDeadLetter::query()->create([
            'service_id' => $serviceId,
            'status' => ProvisioningDeadLetterStatus::PendingReview,
            ...$attributes,
        ]);
    }

    /**
     * Requeue provisioning for a dead-lettered service and close the open letter.
     */
    public function requeue(ProvisioningDeadLetter $letter, ?int $resolvedBy = null, ?string $notes = null): ProvisioningDeadLetter
    {
        if (! $letter->isOpen()) {
            throw new RuntimeException('Only pending_review dead letters can be requeued.');
        }

        return DB::transaction(function () use ($letter, $resolvedBy, $notes): ProvisioningDeadLetter {
            $service = $letter->service()->first();

            if ($service === null) {
                throw new RuntimeException('Dead letter service no longer exists.');
            }

            if (! in_array($service->status, [ServiceStatus::Failed, ServiceStatus::Pending, ServiceStatus::Provisioning], true)) {
                throw new RuntimeException('Service status does not allow provisioning requeue.');
            }

            $letter->forceFill([
                'status' => ProvisioningDeadLetterStatus::Requeued,
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolution_notes' => $notes,
            ])->save();

            $this->engine->queue($service);

            return $letter->fresh(['service']) ?? $letter;
        });
    }

    public function resolve(ProvisioningDeadLetter $letter, ?int $resolvedBy = null, ?string $notes = null): ProvisioningDeadLetter
    {
        return $this->close($letter, ProvisioningDeadLetterStatus::Resolved, $resolvedBy, $notes);
    }

    public function discard(ProvisioningDeadLetter $letter, ?int $resolvedBy = null, ?string $notes = null): ProvisioningDeadLetter
    {
        return $this->close($letter, ProvisioningDeadLetterStatus::Discarded, $resolvedBy, $notes);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ProvisioningDeadLetter>
     */
    public function pending()
    {
        return ProvisioningDeadLetter::query()
            ->where('status', ProvisioningDeadLetterStatus::PendingReview)
            ->with(['service'])
            ->orderByDesc('failed_at')
            ->get();
    }

    private function close(
        ProvisioningDeadLetter $letter,
        ProvisioningDeadLetterStatus $status,
        ?int $resolvedBy,
        ?string $notes,
    ): ProvisioningDeadLetter {
        if (! $letter->isOpen()) {
            throw new RuntimeException('Only pending_review dead letters can be closed.');
        }

        $letter->forceFill([
            'status' => $status,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
            'resolution_notes' => $notes,
        ])->save();

        return $letter->fresh() ?? $letter;
    }
}
