<?php

namespace Core\Automation\DataTransferObjects;

use Core\Automation\Models\AutomationRule;

/**
 * Mutable run state while an automation rule executes its action.
 */
final class RuleRunContext
{
    /**
     * @param  array<string, mixed>  $bag
     * @param  array<string, mixed>|null  $actionResult
     */
    public function __construct(
        public readonly AutomationRule $rule,
        public readonly ?AutomationEventContext $event,
        public array $bag = [],
        public ?array $actionResult = null,
    ) {
        if ($this->bag === [] && $event !== null) {
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
