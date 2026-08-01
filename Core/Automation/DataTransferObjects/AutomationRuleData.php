<?php

namespace Core\Automation\DataTransferObjects;

final readonly class AutomationRuleData
{
    /**
     * @param  array<string, mixed>  $conditionJson
     * @param  array<string, mixed>  $actionJson
     */
    public function __construct(
        public string $name,
        public array $conditionJson,
        public array $actionJson,
        public int $priority,
        public bool $isActive,
    ) {
    }
}
