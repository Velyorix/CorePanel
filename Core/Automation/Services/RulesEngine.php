<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\AutomationEventContext;
use Core\Automation\DataTransferObjects\RuleRunContext;
use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Models\AutomationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        $log = AutomationLog::query()->create([
            'automation_rule_id' => $rule->id,
            'trigger_event' => $trigger,
            'status' => AutomationLogStatus::Running,
            'attempt' => 1,
            'payload' => [
                'event' => $trigger,
                'data' => $data,
                'rule' => $rule->name,
            ],
            'idempotency_key' => (string) Str::uuid(),
            'started_at' => now(),
        ]);

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
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => AutomationLogStatus::Failed,
                'result' => [
                    'action' => $run->actionResult,
                    'bag' => $run->bag,
                ],
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $log->fresh() ?? $log;
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
            return $actionJson;
        }

        if (isset($actionJson['actions']) && is_array($actionJson['actions'])) {
            throw new RuntimeException('Multiple rule actions are not supported; use a single action type.');
        }

        throw new InvalidArgumentException('Rule action_json must contain a type.');
    }
}
