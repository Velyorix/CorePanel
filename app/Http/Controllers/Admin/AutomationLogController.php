<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Automation\IndexAutomationLogRequest;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Services\AutomationLogQueryService;
use Core\Automation\Services\AutomationRetryService;
use Core\Automation\Support\AutomationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class AutomationLogController extends Controller
{
    public function __construct(
        private readonly AutomationLogQueryService $logs,
        private readonly AutomationRetryService $retries,
    ) {
    }

    public function index(IndexAutomationLogRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.automation.logs.index', [
            'logs' => $this->logs->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => AutomationLogStatus::cases(),
            'events' => AutomationEvent::values(),
        ]);
    }

    public function show(AutomationLog $automationLog): View
    {
        Gate::authorize('view', $automationLog);

        $log = $this->logs->findForAdmin($automationLog->id);

        return view('admin.automation.logs.show', [
            'log' => $log,
        ]);
    }

    public function retry(AutomationLog $automationLog): RedirectResponse
    {
        Gate::authorize('retry', $automationLog);

        if ($automationLog->status !== AutomationLogStatus::Retrying) {
            return back()->withErrors(['log' => __('Only retrying runs can be retried manually.')]);
        }

        try {
            $automationLog->forceFill([
                'next_retry_at' => now()->subSecond(),
            ])->save();

            $this->retries->retry($automationLog->fresh() ?? $automationLog);
        } catch (Throwable $exception) {
            return back()->withErrors(['log' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.automation.logs.show', $automationLog)
            ->with('status', __('Automation retry executed.'));
    }
}
