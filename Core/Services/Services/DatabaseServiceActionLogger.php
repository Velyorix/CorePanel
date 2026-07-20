<?php

namespace Core\Services\Services;

use Core\Services\Contracts\ServiceActionLogger;
use Core\Services\Enums\ServiceAction;
use Core\Services\Enums\ServiceActionLogStatus;
use Core\Services\Models\Service;
use Core\Services\Models\ServiceActionLog;

class DatabaseServiceActionLogger implements ServiceActionLogger
{
    public function record(
        Service $service,
        ServiceAction $action,
        ServiceActionLogStatus $status,
        ?int $performedBy = null,
        ?array $response = null,
    ): ServiceActionLog {
        $log = new ServiceActionLog([
            'service_id' => $service->id,
            'action' => $action,
            'status' => $status,
            'response' => $response,
            'performed_by' => $performedBy,
            'created_at' => now(),
        ]);

        $log->save();

        return $log;
    }
}
