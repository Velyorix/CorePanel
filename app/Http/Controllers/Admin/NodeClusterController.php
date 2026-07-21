<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexNodeClusterRequest;
use App\Http\Requests\Admin\StoreNodeClusterRequest;
use App\Http\Requests\Admin\UpdateNodeClusterRequest;
use Core\Nodes\Enums\NodeClusterStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeCluster;
use Core\Nodes\Services\NodeClusterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class NodeClusterController extends Controller
{
    public function __construct(
        private readonly NodeClusterService $clusterService,
    ) {
    }

    public function index(IndexNodeClusterRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.node-clusters.index', [
            'clusters' => $this->clusterService->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => NodeClusterStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', NodeCluster::class);

        return view('admin.node-clusters.create', $this->formData());
    }

    public function store(StoreNodeClusterRequest $request): RedirectResponse
    {
        try {
            $cluster = $this->clusterService->create($request->clusterData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['cluster' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.node-clusters.show', $cluster)
            ->with('status', __('Node cluster created successfully.'));
    }

    public function show(NodeCluster $nodeCluster): View
    {
        Gate::authorize('view', $nodeCluster);

        $nodeCluster->load(['nodes' => fn ($query) => $query->orderBy('name')])
            ->loadCount('nodes');

        return view('admin.node-clusters.show', [
            'cluster' => $nodeCluster,
        ]);
    }

    public function edit(NodeCluster $nodeCluster): View
    {
        Gate::authorize('update', $nodeCluster);

        $nodeCluster->load('nodes');

        return view('admin.node-clusters.edit', array_merge($this->formData($nodeCluster), [
            'cluster' => $nodeCluster,
        ]));
    }

    public function update(UpdateNodeClusterRequest $request, NodeCluster $nodeCluster): RedirectResponse
    {
        try {
            $cluster = $this->clusterService->update($nodeCluster, $request->clusterData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['cluster' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.node-clusters.show', $cluster)
            ->with('status', __('Node cluster updated successfully.'));
    }

    public function destroy(NodeCluster $nodeCluster): RedirectResponse
    {
        Gate::authorize('delete', $nodeCluster);

        try {
            $this->clusterService->delete($nodeCluster);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['cluster' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.node-clusters.index')
            ->with('status', __('Node cluster deleted successfully.'));
    }

    /**
     * @return array{
     *     statuses: list<NodeClusterStatus>,
     *     availableNodes: \Illuminate\Database\Eloquent\Collection<int, Node>,
     *     selectedNodeIds: list<int>
     * }
     */
    private function formData(?NodeCluster $cluster = null): array
    {
        $selectedNodeIds = [];

        if ($cluster !== null) {
            $selectedNodeIds = $cluster->nodes->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [
            'statuses' => NodeClusterStatus::cases(),
            'availableNodes' => Node::query()->orderBy('name')->orderBy('hostname')->get(),
            'selectedNodeIds' => $selectedNodeIds,
        ];
    }
}
