<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeMonitoringService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NodeMonitoringController extends Controller
{
    public function __construct(
        private readonly NodeMonitoringService $monitoring,
    ) {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Node::class);

        return view('admin.nodes.monitoring', [
            'dashboard' => $this->monitoring->dashboard(),
        ]);
    }
}
