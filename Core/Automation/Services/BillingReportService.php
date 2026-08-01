<?php

namespace Core\Automation\Services;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Support\Facades\Log;

class BillingReportService
{
    /**
     * @return array{
     *     unpaid: int,
     *     overdue: int,
     *     paid_today: int,
     *     active_services: int,
     *     suspended_services: int
     * }
     */
    public function generateDaily(): array
    {
        $report = [
            'unpaid' => Invoice::query()->where('status', InvoiceStatus::Unpaid)->count(),
            'overdue' => Invoice::query()->where('status', InvoiceStatus::Overdue)->count(),
            'paid_today' => Invoice::query()
                ->where('status', InvoiceStatus::Paid)
                ->whereDate('paid_at', now()->toDateString())
                ->count(),
            'active_services' => Service::query()->where('status', ServiceStatus::Active)->count(),
            'suspended_services' => Service::query()->where('status', ServiceStatus::Suspended)->count(),
        ];

        Log::info('automation.report.billing', $report);

        return $report;
    }
}
