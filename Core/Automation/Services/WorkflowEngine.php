<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\DataTransferObjects\WorkflowRunContext;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\Workflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Match active workflows to bus events, evaluate conditions, and run steps.
 */
class WorkflowEngine
{
    /** @var list<string> */
    private array $listenerIds = [];

    public function __construct(
        private readonly AutomationEventBus $bus,
        private readonly WorkflowConditionEvaluator $conditions,
        private readonly WorkflowActionRegistry $actions,
    ) {
    }

    public function register(): void
    {
        if (! $this->bus->enabled()) {
            return;
        }

        $this->unregister();

        $events = config('corepanel.automation.events', []);
        $aliases = is_array($events) ? array_keys($events) : [];

        foreach ($aliases as $alias) {
            if (! is_string($alias) || trim($alias) === '') {
                continue;
            }

            $this->listenerIds[] = $this->bus->listen(
                $alias,
                function (AutomationEventContext $context): void {
                    $this->handle($context);
                },
                priority: 50,
            );
        }
    }

    public function unregister(): void
    {
        foreach ($this->listenerIds as $listenerId) {
            $this->bus->forget($listenerId);
        }

        $this->listenerIds = [];
    }

    /**
     * @return Collection<int, AutomationLog>
     */
    public function handle(AutomationEventContext $context): Collection
    {
        if (! $this->bus->enabled()) {
            return collect();
        }

        $workflows = Workflow::query()
            ->active()
            ->forEvent($context->event)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $logs = collect();

        foreach ($workflows as $workflow) {
            if (! $this->conditions->matches($workflow->conditions, $context->data)) {
                continue;
            }

            $logs->push($this->run($workflow, $context));
        }

        return $logs;
    }

    public function run(Workflow $workflow, AutomationEventContext $context): AutomationLog
    {
        $log = AutomationLog::query()->create([
            'workflow_id' => $workflow->id,
            'trigger_event' => $context->event,
            'status' => AutomationLogStatus::Running,
            'attempt' => 1,
            'payload' => [
                'event' => $context->event,
                'data' => $context->data,
                'workflow' => $workflow->slug,
            ],
            'idempotency_key' => (string) Str::uuid(),
            'started_at' => now(),
        ]);

        $run = new WorkflowRunContext($workflow, $context);

        try {
            $steps = is_array($workflow->steps) ? $workflow->steps : [];

            foreach (array_values($steps) as $index => $step) {
                if (! is_array($step)) {
                    throw new RuntimeException("Workflow step #{$index} must be an object.");
                }

                $type = (string) ($step['type'] ?? 'unknown');
                $result = $this->actions->run($step, $run);
                $run->stepResults[] = [
                    'index' => $index,
                    'type' => $type,
                    'result' => $result,
                ];
            }

            $log->forceFill([
                'status' => AutomationLogStatus::Succeeded,
                'result' => [
                    'steps' => $run->stepResults,
                    'bag' => $run->bag,
                ],
                'error_message' => null,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => AutomationLogStatus::Failed,
                'result' => [
                    'steps' => $run->stepResults,
                    'bag' => $run->bag,
                ],
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $log->fresh() ?? $log;
    }
}
