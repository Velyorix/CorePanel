<?php

namespace Core\Services\Services;

use Core\Services\Contracts\ModuleActionDispatcher;
use Core\Services\Enums\ServiceAction;
use Core\Services\Models\Service;

/**
 * Placeholder until module providers are registered.
 */
class NullModuleActionDispatcher implements ModuleActionDispatcher
{
    public function dispatch(Service $service, ServiceAction $action): array
    {
        return [
            'status' => 'skipped',
            'response' => [
                'reason' => 'No module provider registered.',
                'module' => $service->module,
                'action' => $action->value,
            ],
        ];
    }
}
