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
        private readonly AutomationFailureHandler $failures,
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

        return $this->execute($log, $workflow, $context);
    }

    public function retry(AutomationLog $log): AutomationLog
    {
        $workflow = $log->workflow ?? Workflow::query()->find($log->workflow_id);

        if ($workflow === null) {
            throw new RuntimeException("Workflow for automation log [{$log->id}] was not found.");
        }

        $payload = is_array($log->payload) ? $log->payload : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $event = (string) ($payload['event'] ?? $log->trigger_event);

        $context = AutomationEventContext::make($event, $data);

        $log->forceFill([
            'status' => AutomationLogStatus::Running,
            'attempt' => $log->attempt + 1,
            'next_retry_at' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        return $this->execute($log->fresh() ?? $log, $workflow, $context);
    }

    private function execute(AutomationLog $log, Workflow $workflow, AutomationEventContext $context): AutomationLog
    {
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
                'next_retry_at' => null,
                'finished_at' => now(),
            ])->save();

            return $log->fresh() ?? $log;
        } catch (Throwable $exception) {
            $partial = [
                'steps' => $run->stepResults,
                'bag' => $run->bag,
            ];

            $log->forceFill(['result' => $partial])->save();

            $fallback = $this->resolveFallback($workflow);
            $log = $this->failures->handle($log->fresh() ?? $log, $exception, $fallback);

            if ($log->status === AutomationLogStatus::Fallback && ($log->result['fallback_pending'] ?? false)) {
                return $this->runFallback($log, $workflow, $context, $exception, $run);
            }

            return $log;
        }
    }

    private function runFallback(
        AutomationLog $log,
        Workflow $workflow,
        AutomationEventContext $context,
        Throwable $primary,
        WorkflowRunContext $failedRun,
    ): AutomationLog {
        $fallback = $this->resolveFallback($workflow);

        if ($fallback === null) {
            return $this->failures->markFallbackFailed($log, $primary, new RuntimeException('Fallback is missing.'), [
                'steps' => $failedRun->stepResults,
                'bag' => $failedRun->bag,
            ]);
        }

        try {
            $fallbackRun = new WorkflowRunContext($workflow, $context);
            $fallbackRun->bag = $failedRun->bag;
            $result = $this->actions->run($fallback, $fallbackRun);

            return $this->failures->markFallbackSucceeded($log, [
                'type' => $fallback['type'] ?? null,
                'result' => $result,
                'bag' => $fallbackRun->bag,
            ], $primary);
        } catch (Throwable $fallbackError) {
            return $this->failures->markFallbackFailed($log, $primary, $fallbackError, [
                'steps' => $failedRun->stepResults,
                'bag' => $failedRun->bag,
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFallback(Workflow $workflow): ?array
    {
        $fallback = $workflow->fallback;

        if (! is_array($fallback) || $fallback === [] || ! isset($fallback['type'])) {
            return null;
        }

        return $fallback;
    }
}
