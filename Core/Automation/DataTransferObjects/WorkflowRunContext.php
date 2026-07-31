<?php

namespace Core\Automation\DataTransferObjects;

use Core\Automation\Models\Workflow;

/**
 * Mutable run state while a workflow executes its steps.
 */
final class WorkflowRunContext
{
    /**
     * @param  array<string, mixed>  $bag
     * @param  list<array<string, mixed>>  $stepResults
     */
    public function __construct(
        public readonly Workflow $workflow,
        public readonly AutomationEventContext $event,
        public array $bag = [],
        public array $stepResults = [],
    ) {
        if ($this->bag === []) {
            $this->bag = $event->data;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->bag, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        data_set($this->bag, $key, $value);
    }
}
