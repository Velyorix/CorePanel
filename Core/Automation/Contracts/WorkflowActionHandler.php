<?php

namespace Core\Automation\Contracts;

use Core\Automation\DataTransferObjects\WorkflowRunContext;

interface WorkflowActionHandler
{
    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    public function handle(array $step, WorkflowRunContext $context): array;
}
