<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexNodeGroupRequest;
use App\Http\Requests\Admin\StoreNodeGroupRequest;
use App\Http\Requests\Admin\UpdateNodeGroupRequest;
use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
use Core\Nodes\Services\NodeGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class NodeGroupController extends Controller
{
    public function __construct(
        private readonly NodeGroupService $groupService,
    ) {
    }

    public function index(IndexNodeGroupRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.node-groups.index', [
            'groups' => $this->groupService->paginateForAdmin($filters),
            'filters' => $filters,
            'types' => NodeGroupType::cases(),
            'statuses' => NodeGroupStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', NodeGroup::class);

        return view('admin.node-groups.create', $this->formData());
    }

    public function store(StoreNodeGroupRequest $request): RedirectResponse
    {
        try {
            $group = $this->groupService->create($request->groupData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['group' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.node-groups.show', $group)
            ->with('status', __('Node group created successfully.'));
    }

    public function show(NodeGroup $nodeGroup): View
    {
        Gate::authorize('view', $nodeGroup);

        $nodeGroup->load(['assignedNodes' => fn ($query) => $query->orderBy('name')])
            ->loadCount(['assignedNodes', 'provisioningRules']);

        return view('admin.node-groups.show', [
            'group' => $nodeGroup,
        ]);
    }

    public function edit(NodeGroup $nodeGroup): View
    {
        Gate::authorize('update', $nodeGroup);

        $nodeGroup->load('assignedNodes');

        return view('admin.node-groups.edit', array_merge($this->formData($nodeGroup), [
            'group' => $nodeGroup,
        ]));
    }

    public function update(UpdateNodeGroupRequest $request, NodeGroup $nodeGroup): RedirectResponse
    {
        try {
            $group = $this->groupService->update($nodeGroup, $request->groupData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['group' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.node-groups.show', $group)
            ->with('status', __('Node group updated successfully.'));
    }

    public function destroy(NodeGroup $nodeGroup): RedirectResponse
    {
        Gate::authorize('delete', $nodeGroup);

        try {
            $this->groupService->delete($nodeGroup);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['group' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.node-groups.index')
            ->with('status', __('Node group deleted successfully.'));
    }

    /**
     * @return array{
     *     types: list<NodeGroupType>,
     *     statuses: list<NodeGroupStatus>,
     *     availableNodes: \Illuminate\Database\Eloquent\Collection<int, Node>,
     *     selectedNodeIds: list<int>
     * }
     */
    private function formData(?NodeGroup $group = null): array
    {
        $selectedNodeIds = [];

        if ($group !== null) {
            $selectedNodeIds = $group->assignedNodes->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [
            'types' => NodeGroupType::cases(),
            'statuses' => NodeGroupStatus::cases(),
            'availableNodes' => Node::query()->orderBy('name')->orderBy('hostname')->get(),
            'selectedNodeIds' => $selectedNodeIds,
        ];
    }
}
