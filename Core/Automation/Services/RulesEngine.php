<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\DataTransferObjects\RuleRunContext;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Conditional if/then rules engine.
 *
 * Rules may optionally declare an event scope in condition_json:
 * {"event":"invoice.overdue","all":[...]} — otherwise they match any evaluation context.
 */
class RulesEngine
{
    /** @var list<string> */
    private array $listenerIds = [];

    public function __construct(
        private readonly AutomationEventBus $bus,
        private readonly WorkflowConditionEvaluator $conditions,
        private readonly RuleActionRegistry $actions,
        private readonly AutomationFailureHandler $failures,
        private readonly AutomationIdempotencyGuard $idempotency,
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
                priority: 60,
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
     * Evaluate active rules against a bus event context.
     *
     * @return Collection<int, AutomationLog>
     */
    public function handle(AutomationEventContext $context): Collection
    {
        return $this->evaluate($context->data, $context->event, $context);
    }

    /**
     * Evaluate active rules against an arbitrary data bag (scheduler / manual).
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, AutomationLog>
     */
    public function evaluate(array $data, ?string $event = null, ?AutomationEventContext $context = null): Collection
    {
        if (! $this->bus->enabled()) {
            return collect();
        }

        $rules = AutomationRule::query()
            ->active()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $logs = collect();

        foreach ($rules as $rule) {
            if (! $this->appliesToEvent($rule, $event)) {
                continue;
            }

            $conditionPayload = $this->conditionsWithoutEventMeta($rule->condition_json);

            if (! $this->conditions->matches($conditionPayload, $data)) {
                continue;
            }

            $logs->push($this->run($rule, $data, $event, $context));
        }

        return $logs;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function run(
        AutomationRule $rule,
        array $data,
        ?string $event = null,
        ?AutomationEventContext $context = null,
    ): AutomationLog {
        $trigger = $event ?? $context?->event ?? 'rule.evaluate';
        $key = $this->idempotency->keys()->forRule($rule->id, $trigger, $data);

        $claim = $this->idempotency->claimOrExisting($key, function () use ($rule, $data, $trigger, $key): AutomationLog {
            return AutomationLog::query()->create([
                'automation_rule_id' => $rule->id,
                'trigger_event' => $trigger,
                'status' => AutomationLogStatus::Running,
                'attempt' => 1,
                'payload' => [
                    'event' => $trigger,
                    'data' => $data,
                    'rule' => $rule->name,
                ],
                'idempotency_key' => $key,
                'started_at' => now(),
            ]);
        });

        if (! $claim->isNew) {
            return $claim->log;
        }

        return $this->execute($claim->log, $rule, $data, $event, $context);
    }

    public function retry(AutomationLog $log): AutomationLog
    {
        $rule = $log->automationRule ?? AutomationRule::query()->find($log->automation_rule_id);

        if ($rule === null) {
            throw new RuntimeException("Rule for automation log [{$log->id}] was not found.");
        }

        $payload = is_array($log->payload) ? $log->payload : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $event = isset($payload['event']) && is_string($payload['event']) ? $payload['event'] : $log->trigger_event;

        $log->forceFill([
            'status' => AutomationLogStatus::Running,
            'attempt' => $log->attempt + 1,
            'next_retry_at' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        return $this->execute($log->fresh() ?? $log, $rule, $data, $event, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function execute(
        AutomationLog $log,
        AutomationRule $rule,
        array $data,
        ?string $event = null,
        ?AutomationEventContext $context = null,
    ): AutomationLog {
        $run = new RuleRunContext($rule, $context, $data);

        try {
            $action = $this->normalizeAction($rule->action_json);
            $result = $this->actions->run($action, $run);
            $run->actionResult = $result;

            $log->forceFill([
                'status' => AutomationLogStatus::Succeeded,
                'result' => [
                    'action' => [
                        'type' => $action['type'] ?? null,
                        'result' => $result,
                    ],
                    'bag' => $run->bag,
                ],
                'error_message' => null,
                'next_retry_at' => null,
                'finished_at' => now(),
            ])->save();

            return $log->fresh() ?? $log;
        } catch (Throwable $exception) {
            $partial = [
                'action' => $run->actionResult,
                'bag' => $run->bag,
            ];

            $log->forceFill(['result' => $partial])->save();

            $fallback = $this->resolveFallback($rule);
            $log = $this->failures->handle($log->fresh() ?? $log, $exception, $fallback);

            if ($log->status === AutomationLogStatus::Fallback && ($log->result['fallback_pending'] ?? false)) {
                return $this->runFallback($log, $rule, $data, $context, $exception, $run);
            }

            return $log;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function runFallback(
        AutomationLog $log,
        AutomationRule $rule,
        array $data,
        ?AutomationEventContext $context,
        Throwable $primary,
        RuleRunContext $failedRun,
    ): AutomationLog {
        $fallback = $this->resolveFallback($rule);

        if ($fallback === null) {
            return $this->failures->markFallbackFailed($log, $primary, new RuntimeException('Fallback is missing.'), [
                'action' => $failedRun->actionResult,
                'bag' => $failedRun->bag,
            ]);
        }

        try {
            $fallbackRun = new RuleRunContext($rule, $context, $data);
            $fallbackRun->bag = $failedRun->bag;
            $result = $this->actions->run($fallback, $fallbackRun);

            return $this->failures->markFallbackSucceeded($log, [
                'type' => $fallback['type'] ?? null,
                'result' => $result,
                'bag' => $fallbackRun->bag,
            ], $primary);
        } catch (Throwable $fallbackError) {
            return $this->failures->markFallbackFailed($log, $primary, $fallbackError, [
                'action' => $failedRun->actionResult,
                'bag' => $failedRun->bag,
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFallback(AutomationRule $rule): ?array
    {
        $action = is_array($rule->action_json) ? $rule->action_json : [];
        $fallback = $action['fallback'] ?? null;

        if (! is_array($fallback) || $fallback === [] || ! isset($fallback['type'])) {
            return null;
        }

        return $fallback;
    }

    private function appliesToEvent(AutomationRule $rule, ?string $event): bool
    {
        $conditions = is_array($rule->condition_json) ? $rule->condition_json : [];
        $required = $conditions['event'] ?? $conditions['trigger_event'] ?? null;

        if (! is_string($required) || trim($required) === '' || $required === '*') {
            return true;
        }

        if ($event === null) {
            return false;
        }

        return $event === $required;
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @return array<string, mixed>|null
     */
    private function conditionsWithoutEventMeta(?array $conditions): ?array
    {
        if ($conditions === null) {
            return null;
        }

        $filtered = $conditions;
        unset($filtered['event'], $filtered['trigger_event']);

        return $filtered;
    }

    /**
     * @param  array<string, mixed>|null  $actionJson
     * @return array<string, mixed>
     */
    private function normalizeAction(?array $actionJson): array
    {
        if ($actionJson === null || $actionJson === []) {
            throw new InvalidArgumentException('Rule action_json cannot be empty.');
        }

        if (isset($actionJson['type']) && is_string($actionJson['type'])) {
            $action = $actionJson;
            unset($action['fallback']);

            return $action;
        }

        if (isset($actionJson['actions']) && is_array($actionJson['actions'])) {
            throw new RuntimeException('Multiple rule actions are not supported; use a single action type.');
        }

        throw new InvalidArgumentException('Rule action_json must contain a type.');
    }
}
