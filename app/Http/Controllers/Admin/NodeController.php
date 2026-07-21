<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexNodeRequest;
use App\Http\Requests\Admin\StoreNodeRequest;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Nodes\Models\NodeGroup;
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

        return redirect()
            ->route('admin.nodes.index')
            ->with('status', __('Server created successfully.'));
    }

    public function show(Node $node): View
    {
        Gate::authorize('view', $node);

        $found = $this->nodeService->find($node->id);

        return view('admin.nodes.show', [
            'node' => $found ?? $node,
        ]);
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
