<?php

namespace Core\Automation\Contracts;

use Core\Automation\DataTransferObjects\RuleRunContext;

interface RuleActionHandler
{
    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public function handle(array $action, RuleRunContext $context): array;
}
