<?php

namespace Core\Services\Contracts;

use Core\Services\Enums\ServiceAction;
use Core\Services\Models\Service;

interface ModuleActionDispatcher
{
    /**
     * Dispatch a control action to the service module provider.
     *
     * @return array{status: string, response: array<string, mixed>}
     */
    public function dispatch(Service $service, ServiceAction $action): array;
}
