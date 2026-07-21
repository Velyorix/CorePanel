<?php

namespace Core\Provisioning\Services;

use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        private readonly ProvisioningRollbackService $rollback,
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
     * @param  array{
     *     q?: string|null,
     *     status?: ProvisioningDeadLetterStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'failed_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['id', 'status', 'module', 'attempts', 'failed_at', 'created_at'], true)) {
            $sort = 'failed_at';
        }

        $query = ProvisioningDeadLetter::query()->with(['service.client', 'service.product', 'resolver']);

        if ($status instanceof ProvisioningDeadLetterStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('module', 'like', $term)
                    ->orWhere('exception_message', 'like', $term)
                    ->orWhere('exception_class', 'like', $term)
                    ->orWhereHas('service', function ($serviceQuery) use ($term): void {
                        $serviceQuery
                            ->where('hostname', 'like', $term)
                            ->orWhere('external_id', 'like', $term)
                            ->orWhereHas('client', function ($clientQuery) use ($term): void {
                                $clientQuery->where('company_name', 'like', $term);
                            });
                    });
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Requeue provisioning for a dead-lettered service and close the open letter.
     *
     * @param  array{release_node?: bool, rollback?: bool}  $options
     */
    public function requeue(
        ProvisioningDeadLetter $letter,
        ?int $resolvedBy = null,
        ?string $notes = null,
        array $options = [],
    ): ProvisioningDeadLetter {
        if (! $letter->isOpen()) {
            throw new RuntimeException('Only pending_review dead letters can be requeued.');
        }

        $rollback = (bool) ($options['rollback'] ?? true);
        $releaseNode = (bool) ($options['release_node'] ?? false);

        return DB::transaction(function () use ($letter, $resolvedBy, $notes, $rollback, $releaseNode): ProvisioningDeadLetter {
            $service = $letter->service()->first();

            if ($service === null) {
                throw new RuntimeException('Dead letter service no longer exists.');
            }

            if (! in_array($service->status, [ServiceStatus::Failed, ServiceStatus::Pending, ServiceStatus::Provisioning], true)) {
                throw new RuntimeException('Service status does not allow provisioning requeue.');
            }

            if ($rollback) {
                $this->rollback->rollbackLocalState($service, [
                    'clear_mapping' => true,
                    'clear_external_id' => true,
                    'clear_node' => $releaseNode,
                ]);
                $service = $service->fresh() ?? $service;
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

    /**
     * Clear sticky local provisioning state without closing the dead letter.
     *
     * @param  array{clear_node?: bool, clear_network_identity?: bool}  $options
     * @return array{cleared: list<string>}
     */
    public function rollback(ProvisioningDeadLetter $letter, array $options = []): array
    {
        if (! $letter->isOpen()) {
            throw new RuntimeException('Only pending_review dead letters can be rolled back.');
        }

        $service = $letter->service()->first();

        if ($service === null) {
            throw new RuntimeException('Dead letter service no longer exists.');
        }

        return $this->rollback->rollbackLocalState($service, [
            'clear_mapping' => true,
            'clear_external_id' => true,
            'clear_node' => (bool) ($options['clear_node'] ?? true),
            'clear_network_identity' => (bool) ($options['clear_network_identity'] ?? false),
        ]);
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
