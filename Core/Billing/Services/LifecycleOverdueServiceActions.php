<?php

namespace Core\Billing\Services;

use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\Models\Invoice;
use Core\Services\Models\Service;
use Core\Services\Services\ServiceControlService;

/**
 * Bridges overdue invoice automation to the services control layer.
 */
class LifecycleOverdueServiceActions implements OverdueServiceActions
{
    public function __construct(
        private readonly ServiceControlService $control,
    ) {
    }

    public function suspend(Invoice $invoice, int $serviceId, string $reason): void
    {
        $service = Service::query()->find($serviceId);

        if ($service === null) {
            return;
        }

        $this->control->suspend($service);
    }

    public function terminate(Invoice $invoice, int $serviceId, string $reason): void
    {
        $service = Service::query()->find($serviceId);

        if ($service === null) {
            return;
        }

        $this->control->terminate($service);
    }
}
