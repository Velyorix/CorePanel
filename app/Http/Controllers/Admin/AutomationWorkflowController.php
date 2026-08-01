<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Automation\IndexWorkflowRequest;
use App\Http\Requests\Admin\Automation\StoreWorkflowRequest;
use App\Http\Requests\Admin\Automation\UpdateWorkflowRequest;
use Core\Automation\Models\Workflow;
use Core\Automation\Services\WorkflowAdminService;
use Core\Automation\Support\AutomationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class AutomationWorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowAdminService $workflows,
    ) {
    }

    public function index(IndexWorkflowRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.automation.workflows.index', [
            'workflows' => $this->workflows->paginateForAdmin($filters),
            'filters' => $filters,
            'events' => AutomationEvent::values(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Workflow::class);

        return view('admin.automation.workflows.create', [
            'events' => AutomationEvent::values(),
        ]);
    }

    public function store(StoreWorkflowRequest $request): RedirectResponse
    {
        try {
            $workflow = $this->workflows->create($request->workflowData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.automation.workflows.show', $workflow)
            ->with('status', __('Workflow created successfully.'));
    }

    public function show(Workflow $workflow): View
    {
        Gate::authorize('view', $workflow);

        $workflow->loadCount('logs');

        return view('admin.automation.workflows.show', [
            'workflow' => $workflow,
        ]);
    }

    public function edit(Workflow $workflow): View
    {
        Gate::authorize('update', $workflow);

        return view('admin.automation.workflows.edit', [
            'workflow' => $workflow,
            'events' => AutomationEvent::values(),
        ]);
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow): RedirectResponse
    {
        try {
            $workflow = $this->workflows->update($workflow, $request->workflowData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.automation.workflows.show', $workflow)
            ->with('status', __('Workflow updated successfully.'));
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        Gate::authorize('delete', $workflow);

        $this->workflows->delete($workflow);

        return redirect()
            ->route('admin.automation.workflows.index')
            ->with('status', __('Workflow deleted successfully.'));
    }
}
