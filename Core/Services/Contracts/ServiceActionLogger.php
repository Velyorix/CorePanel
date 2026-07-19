<?php

namespace Core\Services\Contracts;

use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;

interface ServiceActionLogger
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function record(
        Service $service,
        ServiceAction $action,
        ServiceActionLogStatus $status,
        ?int $performedBy = null,
        ?array $response = null,
    ): ServiceActionLog;
}
