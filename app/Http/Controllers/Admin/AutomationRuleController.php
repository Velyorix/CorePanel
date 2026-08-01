<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Automation\IndexAutomationRuleRequest;
use App\Http\Requests\Admin\Automation\StoreAutomationRuleRequest;
use App\Http\Requests\Admin\Automation\UpdateAutomationRuleRequest;
use Core\Automation\Models\AutomationRule;
use Core\Automation\Services\AutomationRuleAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class AutomationRuleController extends Controller
{
    public function __construct(
        private readonly AutomationRuleAdminService $rules,
    ) {
    }

    public function index(IndexAutomationRuleRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.automation.rules.index', [
            'rules' => $this->rules->paginateForAdmin($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AutomationRule::class);

        return view('admin.automation.rules.create');
    }

    public function store(StoreAutomationRuleRequest $request): RedirectResponse
    {
        try {
            $rule = $this->rules->create($request->ruleData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['rule' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.automation.rules.show', $rule)
            ->with('status', __('Automation rule created successfully.'));
    }

    public function show(AutomationRule $automationRule): View
    {
        Gate::authorize('view', $automationRule);

        $automationRule->loadCount('logs');

        return view('admin.automation.rules.show', [
            'rule' => $automationRule,
        ]);
    }

    public function edit(AutomationRule $automationRule): View
    {
        Gate::authorize('update', $automationRule);

        return view('admin.automation.rules.edit', [
            'rule' => $automationRule,
        ]);
    }

    public function update(UpdateAutomationRuleRequest $request, AutomationRule $automationRule): RedirectResponse
    {
        try {
            $rule = $this->rules->update($automationRule, $request->ruleData());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['rule' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.automation.rules.show', $rule)
            ->with('status', __('Automation rule updated successfully.'));
    }

    public function destroy(AutomationRule $automationRule): RedirectResponse
    {
        Gate::authorize('delete', $automationRule);

        $this->rules->delete($automationRule);

        return redirect()
            ->route('admin.automation.rules.index')
            ->with('status', __('Automation rule deleted successfully.'));
    }
}
