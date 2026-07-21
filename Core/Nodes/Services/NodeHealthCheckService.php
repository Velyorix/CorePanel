<?php

namespace Core\Nodes\Services;

use Core\Nodes\DataTransferObjects\NodeHealthCheckBatchResult;
use Core\Nodes\Enums\NodeHealthState;
use Core\Nodes\Enums\NodeLogStatus;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeHealthCheck;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class NodeHealthCheckService
{
    public function __construct(
        private readonly NodeConnectionTestService $connectionTest,
        private readonly NodeCapacityService $capacity,
        private readonly NodeAllocationAlgorithm $allocation,
        private readonly NodeLogService $nodeLogs,
    ) {
    }

    public function checkAll(): NodeHealthCheckBatchResult
    {
        if (! (bool) config('corepanel.nodes.health.enabled', true)) {
            return new NodeHealthCheckBatchResult;
        }

        $online = 0;
        $degraded = 0;
        $offline = 0;
        $skipped = 0;

        foreach ($this->checkableNodes() as $node) {
            $check = $this->checkForNode($node);

            match ($check->state) {
                NodeHealthState::Online => $online++,
                NodeHealthState::Degraded => $degraded++,
                NodeHealthState::Offline => $offline++,
                NodeHealthState::Skipped => $skipped++,
                default => null,
            };
        }

        return new NodeHealthCheckBatchResult(
            online: $online,
            degraded: $degraded,
            offline: $offline,
            skipped: $skipped,
        );
    }

    public function checkForNode(Node $node): NodeHealthCheck
    {
        $checkedAt = now();

        if ($node->status === NodeStatus::Disabled) {
            return $this->finalizeCheck(
                node: $node,
                state: NodeHealthState::Skipped,
                checkedAt: $checkedAt,
                message: 'node_disabled',
            );
        }

        $module = trim((string) ($node->module ?? ''));

        if ($module === '') {
            return $this->finalizeCheck(
                node: $node,
                state: NodeHealthState::Skipped,
                checkedAt: $checkedAt,
                message: 'missing_module',
            );
        }

        $latencyMs = null;

        try {
            $startedAt = microtime(true);
            $response = $this->connectionTest->testNode($node);
            $latencyMs = max(0, (int) round((microtime(true) - $startedAt) * 1000));
        } catch (InvalidArgumentException $exception) {
            return $this->finalizeCheck(
                node: $node,
                state: NodeHealthState::Offline,
                checkedAt: $checkedAt,
                message: $exception->getMessage(),
            );
        }

        if (! $response->status->isSuccessful()) {
            return $this->finalizeCheck(
                node: $node,
                state: NodeHealthState::Offline,
                checkedAt: $checkedAt,
                message: $response->message ?? __('Connection test failed.'),
                payload: $this->responsePayload($response),
                latencyMs: $latencyMs,
            );
        }

        $state = $this->resolveOperationalState($node);

        return $this->finalizeCheck(
            node: $node,
            state: $state,
            checkedAt: $checkedAt,
            message: $response->message,
            payload: $this->responsePayload($response),
            latencyMs: $latencyMs,
        );
    }

    /**
     * @return Collection<int, Node>
     */
    private function checkableNodes(): Collection
    {
        return Node::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->orderBy('id')
            ->get();
    }

    private function resolveOperationalState(Node $node): NodeHealthState
    {
        $usage = $this->capacity->usage($node);

        if ($usage->capacityAvailable === false) {
            return NodeHealthState::Degraded;
        }

        if (! $this->capacity->isUsageFresh($node)) {
            return NodeHealthState::Online;
        }

        $threshold = (float) config('corepanel.nodes.health.degraded_utilization_threshold', 0.85);

        if ($this->allocation->utilizationScore($node) >= $threshold) {
            return NodeHealthState::Degraded;
        }

        return NodeHealthState::Online;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function finalizeCheck(
        Node $node,
        NodeHealthState $state,
        \Illuminate\Support\Carbon $checkedAt,
        ?string $message = null,
        ?array $payload = null,
        ?int $latencyMs = null,
    ): NodeHealthCheck {
        $check = $this->storeCheck(
            node: $node,
            state: $state,
            checkedAt: $checkedAt,
            message: $message,
            payload: $payload,
            latencyMs: $latencyMs,
        );

        $this->applySnapshot($node, $state, $checkedAt, $message, $latencyMs);

        return $check;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function storeCheck(
        Node $node,
        NodeHealthState $state,
        \Illuminate\Support\Carbon $checkedAt,
        ?string $message = null,
        ?array $payload = null,
        ?int $latencyMs = null,
    ): NodeHealthCheck {
        $check = new NodeHealthCheck([
            'node_id' => $node->id,
            'state' => $state,
            'latency_ms' => $latencyMs,
            'message' => $message,
            'payload' => $payload,
            'checked_at' => $checkedAt,
            'created_at' => now(),
        ]);

        $check->save();

        return $check;
    }

    private function applySnapshot(
        Node $node,
        NodeHealthState $state,
        \Illuminate\Support\Carbon $checkedAt,
        ?string $message,
        ?int $latencyMs,
    ): void {
        $config = is_array($node->config) ? $node->config : [];
        $previousHealth = is_array($config['health'] ?? null) ? $config['health'] : [];
        $autoManaged = (bool) ($previousHealth['auto_managed'] ?? false);
        $previousStatus = $node->status;
        $nextStatus = null;

        $config['health'] = array_filter([
            'state' => $state->value,
            'checked_at' => $checkedAt->toIso8601String(),
            'latency_ms' => $latencyMs,
            'message' => $message,
            'auto_managed' => $autoManaged,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ((bool) config('corepanel.nodes.health.auto_status', true)) {
            [$nextStatus, $autoManaged] = $this->resolveStatusTransition(
                node: $node,
                state: $state,
                autoManaged: $autoManaged,
            );

            $config['health']['auto_managed'] = $autoManaged;
        }

        $attributes = ['config' => $config];

        if ($nextStatus !== null) {
            $attributes['status'] = $nextStatus->value;
        }

        $node->forceFill($attributes)->save();

        if ($nextStatus !== null && $nextStatus !== $previousStatus) {
            $this->nodeLogs->record(
                node: $node->fresh() ?? $node,
                action: 'node.health.status_changed',
                status: NodeLogStatus::Success,
                response: [
                    'from' => $previousStatus->value,
                    'to' => $nextStatus->value,
                    'health_state' => $state->value,
                ],
            );
        }
    }

    /**
     * @return array{0: NodeStatus|null, 1: bool}
     */
    private function resolveStatusTransition(
        Node $node,
        NodeHealthState $state,
        bool $autoManaged,
    ): array {
        if (in_array($node->status, [NodeStatus::Disabled, NodeStatus::Maintenance], true)) {
            return [null, $autoManaged];
        }

        if ($state === NodeHealthState::Offline && $node->status === NodeStatus::Active) {
            return [NodeStatus::Offline, true];
        }

        if (
            $state === NodeHealthState::Online
            && $node->status === NodeStatus::Offline
            && $autoManaged
        ) {
            return [NodeStatus::Active, false];
        }

        return [null, $autoManaged];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function responsePayload(NodeOperationResponse $response): ?array
    {
        $payload = array_filter([
            'message' => $response->message,
            'payload' => $response->payload !== [] ? $response->payload : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $payload === [] ? null : $payload;
    }
}
