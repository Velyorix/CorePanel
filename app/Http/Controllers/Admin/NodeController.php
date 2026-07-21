<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexNodeRequest;
use App\Http\Requests\Admin\StoreNodeRequest;
use Core\Nodes\Enums\NodeLogStatus;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Services\NodeAuditLogger;
use Core\Nodes\Services\NodeLogService;
use Core\Nodes\Services\NodeMonitoringService;
use Core\Nodes\Services\NodeService;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class NodeController extends Controller
{
    public function __construct(
        private readonly NodeService $nodeService,
        private readonly NodeLogService $nodeLogs,
        private readonly NodeAuditLogger $audit,
        private readonly NodeMonitoringService $monitoring,
        private readonly ProviderRegistry $providers,
    ) {
    }

    public function index(IndexNodeRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.nodes.index', [
            'nodes' => $this->nodeService->paginateForAdmin($filters),
            'filters' => $filters,
            'types' => NodeType::cases(),
            'statuses' => NodeStatus::cases(),
            'groups' => NodeGroup::query()->orderBy('sort_order')->orderBy('name')->get(),
            'modules' => $this->moduleOptions(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Node::class);

        return view('admin.nodes.create', $this->formData());
    }

    public function store(StoreNodeRequest $request): RedirectResponse
    {
        try {
            $node = $this->nodeService->create($request->nodeData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['node' => $exception->getMessage()]);
        }

        $this->nodeLogs->record(
            node: $node,
            action: 'node.created',
            status: NodeLogStatus::Success,
            performedBy: auth()->id(),
            response: [
                'module' => $node->module,
                'status' => $node->status->value,
            ],
        );

        $this->audit->log(
            action: NodeAuditLogger::ACTION_CREATED,
            node: $node,
            after: $this->audit->nodeSnapshot($node),
        );

        return redirect()
            ->route('admin.nodes.index')
            ->with('status', __('Server created successfully.'));
    }

    public function show(Node $node): View
    {
        Gate::authorize('view', $node);

        $found = $this->nodeService->find($node->id);
        $resolved = $found ?? $node;

        $this->audit->log(
            action: NodeAuditLogger::ACTION_VIEWED,
            node: $resolved,
        );

        return view('admin.nodes.show', [
            'node' => $resolved,
            'logs' => $this->nodeLogs->forNode($resolved, limit: 20),
            'monitoring' => $this->monitoring->forNode($resolved),
        ]);
    }

    public function sync(Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        try {
            $response = $this->nodeService->sync($node);
        } catch (InvalidArgumentException $exception) {
            $this->nodeLogs->record(
                node: $node,
                action: 'node.sync',
                status: NodeLogStatus::Failed,
                performedBy: auth()->id(),
                response: ['message' => $exception->getMessage()],
            );

            return redirect()
                ->route('admin.nodes.show', $node)
                ->withErrors(['node' => $exception->getMessage()]);
        }

        if ($response->status->isSuccessful()) {
            $fresh = $node->fresh() ?? $node;

            $this->nodeLogs->record(
                node: $fresh,
                action: 'node.sync',
                status: NodeLogStatus::Success,
                performedBy: auth()->id(),
                response: $response->payload,
            );

            $this->audit->log(
                action: NodeAuditLogger::ACTION_SYNCED,
                node: $fresh,
                after: [
                    'message' => $response->message,
                    'payload' => $response->payload,
                ],
            );

            return redirect()
                ->route('admin.nodes.show', $node)
                ->with('status', $response->message ?? __('Node synced successfully.'));
        }

        $this->nodeLogs->record(
            node: $node,
            action: 'node.sync',
            status: NodeLogStatus::Failed,
            performedBy: auth()->id(),
            response: array_filter([
                'message' => $response->message,
                'payload' => $response->payload,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
        );

        return redirect()
            ->route('admin.nodes.show', $node)
            ->withErrors(['node' => $response->message ?? __('Node sync failed.')]);
    }

    public function maintenance(Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        $target = $node->status === NodeStatus::Maintenance
            ? NodeStatus::Active
            : NodeStatus::Maintenance;

        $before = $this->audit->nodeSnapshot($node);

        $this->nodeService->setStatus($node, $target);
        $fresh = $node->fresh() ?? $node;
        $this->nodeLogs->record(
            node: $fresh,
            action: 'node.status.changed',
            status: NodeLogStatus::Success,
            performedBy: auth()->id(),
            response: ['status' => $target->value],
        );

        $this->audit->log(
            action: NodeAuditLogger::ACTION_STATUS_CHANGED,
            node: $fresh,
            before: $before,
            after: $this->audit->nodeSnapshot($fresh),
        );

        return redirect()
            ->route('admin.nodes.show', $node)
            ->with('status', __('Node status updated.'));
    }

    public function disable(Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        $before = $this->audit->nodeSnapshot($node);

        $this->nodeService->setStatus($node, NodeStatus::Disabled);
        $fresh = $node->fresh() ?? $node;
        $this->nodeLogs->record(
            node: $fresh,
            action: 'node.disabled',
            status: NodeLogStatus::Success,
            performedBy: auth()->id(),
            response: ['status' => NodeStatus::Disabled->value],
        );

        $this->audit->log(
            action: NodeAuditLogger::ACTION_STATUS_CHANGED,
            node: $fresh,
            before: $before,
            after: $this->audit->nodeSnapshot($fresh),
        );

        return redirect()
            ->route('admin.nodes.show', $node)
            ->with('status', __('Node disabled.'));
    }

    public function enable(Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        $before = $this->audit->nodeSnapshot($node);

        $this->nodeService->setStatus($node, NodeStatus::Active);
        $fresh = $node->fresh() ?? $node;
        $this->nodeLogs->record(
            node: $fresh,
            action: 'node.enabled',
            status: NodeLogStatus::Success,
            performedBy: auth()->id(),
            response: ['status' => NodeStatus::Active->value],
        );

        $this->audit->log(
            action: NodeAuditLogger::ACTION_STATUS_CHANGED,
            node: $fresh,
            before: $before,
            after: $this->audit->nodeSnapshot($fresh),
        );

        return redirect()
            ->route('admin.nodes.show', $node)
            ->with('status', __('Node enabled.'));
    }

    public function destroy(Node $node): RedirectResponse
    {
        Gate::authorize('delete', $node);

        try {
            $snapshot = $this->audit->nodeSnapshot($node);

            $this->nodeLogs->record(
                node: $node,
                action: 'node.deleted',
                status: NodeLogStatus::Success,
                performedBy: auth()->id(),
            );
            $this->nodeService->delete($node);

            $this->audit->log(
                action: NodeAuditLogger::ACTION_DELETED,
                node: $node,
                before: $snapshot,
            );
        } catch (InvalidArgumentException $exception) {
            $this->nodeLogs->record(
                node: $node,
                action: 'node.delete_failed',
                status: NodeLogStatus::Failed,
                performedBy: auth()->id(),
                response: ['message' => $exception->getMessage()],
            );

            return redirect()
                ->route('admin.nodes.show', $node)
                ->withErrors(['node' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.nodes.index')
            ->with('status', __('Node deleted successfully.'));
    }

    /**
     * @return array{
     *     types: list<NodeType>,
     *     statuses: list<NodeStatus>,
     *     groups: \Illuminate\Database\Eloquent\Collection<int, NodeGroup>,
     *     modules: array<string, string>
     * }
     */
    private function formData(): array
    {
        return [
            'types' => NodeType::cases(),
            'statuses' => NodeStatus::cases(),
            'groups' => NodeGroup::query()->orderBy('sort_order')->orderBy('name')->get(),
            'modules' => $this->moduleOptions(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function moduleOptions(): array
    {
        $options = [];

        foreach ($this->providers->allNodes() as $provider) {
            $options[$provider->key()] ??= $provider->label();
        }

        ksort($options);

        return $options;
    }
}
