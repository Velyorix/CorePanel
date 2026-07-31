<?php

namespace Core\Nodes\Services;

use Core\Nodes\Models\Node;
use Core\Support\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class NodeAuditLogger
{
    public const ACTION_CREATED = 'node.created';

    public const ACTION_VIEWED = 'node.viewed';

    public const ACTION_UPDATED = 'node.updated';

    public const ACTION_DELETED = 'node.deleted';

    public const ACTION_SYNCED = 'node.synced';

    public const ACTION_CONNECTION_TESTED = 'node.connection.tested';

    public const ACTION_CREDENTIALS_UPDATED = 'node.credentials.updated';

    public const ACTION_STATUS_CHANGED = 'node.status.changed';

    public const ACTION_REMOTE_PROBED = 'node.remote.probed';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        Node $node,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->auditLogger->record(
            action: $action,
            actorId: $actorId ?? $this->actorId(),
            entityType: Node::class,
            entityId: $node->id,
            before: $before,
            after: $after,
            ipAddress: $this->ipAddress(),
            userAgent: $this->userAgent(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function nodeSnapshot(Node $node): array
    {
        return [
            'name' => $node->name,
            'hostname' => $node->hostname,
            'module' => $node->module,
            'status' => $node->status->value,
            'ip_address' => $node->ip_address,
            'api_url' => $node->api_url,
            'node_group_id' => $node->node_group_id,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('corepanel.nodes.security.audit.enabled', true);
    }

    private function actorId(): ?int
    {
        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    private function ipAddress(): ?string
    {
        return Request::ip();
    }

    private function userAgent(): ?string
    {
        $userAgent = Request::userAgent();

        return filled($userAgent) ? (string) $userAgent : null;
    }
}
