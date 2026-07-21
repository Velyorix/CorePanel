<?php

namespace Core\Sync\Services;

use Core\Nodes\Models\Node;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\DataTransferObjects\ProvisioningResponse;
use Core\Services\Models\Service;
use Core\Sync\DataTransferObjects\ServiceSyncComparisonResult;
use Core\Sync\DataTransferObjects\ServiceSyncResolutionResult;
use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Core\Sync\Models\SyncLog;

class SyncLogService
{
    public function recordServicePoll(
        Service $service,
        SyncLogOutcome $outcome,
        ?ServiceSyncComparisonResult $comparison = null,
        ?ServiceSyncResolutionResult $resolution = null,
        ?ProvisioningResponse $response = null,
        ?string $message = null,
    ): ?SyncLog {
        if (! $this->isEnabled('service')) {
            return null;
        }

        return SyncLog::query()->create([
            'subject_type' => SyncLogSubject::Service,
            'subject_id' => $service->id,
            'module' => $service->module,
            'outcome' => $outcome,
            'message' => $message,
            'divergences' => $comparison?->hasDivergences()
                ? $comparison->divergenceArrays()
                : null,
            'resolutions' => $resolution?->hasApplied()
                ? $resolution->appliedArrays()
                : null,
            'payload' => $this->servicePayload($response, $resolution),
            'created_at' => now(),
        ]);
    }

    public function recordNodeSync(
        Node $node,
        SyncLogOutcome $outcome,
        ?NodeOperationResponse $response = null,
        ?string $message = null,
    ): ?SyncLog {
        if (! $this->isEnabled('node')) {
            return null;
        }

        return SyncLog::query()->create([
            'subject_type' => SyncLogSubject::Node,
            'subject_id' => $node->id,
            'module' => $node->module,
            'outcome' => $outcome,
            'message' => $message ?? $response?->message,
            'payload' => $response !== null
                ? array_filter([
                    'status' => $response->status->value,
                    'message' => $response->message,
                    'payload' => $response->payload === [] ? null : $response->payload,
                ], static fn (mixed $value): bool => $value !== null)
                : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function servicePayload(
        ?ProvisioningResponse $response,
        ?ServiceSyncResolutionResult $resolution,
    ): ?array {
        $payload = array_filter([
            'remote_status' => $response?->payload['remote_status'] ?? $response?->payload['status'] ?? null,
            'message' => $response?->message,
            'poll_payload' => $response?->payload === [] ? null : $response?->payload,
            'unresolved' => $resolution !== null && $resolution->unresolved !== []
                ? $resolution->unresolvedArrays()
                : null,
        ], static fn (mixed $value): bool => $value !== null);

        return $payload === [] ? null : $payload;
    }

    private function isEnabled(string $subject): bool
    {
        return (bool) config("corepanel.sync.logs.{$subject}.enabled", true);
    }
}
