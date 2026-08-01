<?php

namespace Core\Automation\Services;

use App\Jobs\GenerateRenewalInvoices;
use App\Jobs\ProcessOverdueSuspensions;
use App\Jobs\SendInvoiceReminders;
use Core\Automation\Jobs\CheckInvoicesDueJob;
use Core\Automation\Jobs\CleanupAutomationLogsJob;
use Core\Automation\Jobs\EvaluateScheduledRulesJob;
use Core\Automation\Jobs\GenerateBillingReportJob;
use Core\Automation\Jobs\GenerateSystemHealthReportJob;
use Core\Nodes\Jobs\CollectNodeMetricsJob;
use Core\Nodes\Jobs\RunNodeHealthChecksJob;
use Core\Sync\Jobs\NodeSyncJob;
use Core\Sync\Jobs\ServiceSyncJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FailedSystemJobRetryService
{
    /**
     * @return list<class-string>
     */
    public function allowedJobClasses(): array
    {
        $configured = config('corepanel.automation.scheduler.retry_job_classes');

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(
                $configured,
                static fn (mixed $class): bool => is_string($class) && $class !== '',
            ));
        }

        return [
            CheckInvoicesDueJob::class,
            GenerateRenewalInvoices::class,
            SendInvoiceReminders::class,
            ProcessOverdueSuspensions::class,
            RunNodeHealthChecksJob::class,
            CollectNodeMetricsJob::class,
            ServiceSyncJob::class,
            NodeSyncJob::class,
            EvaluateScheduledRulesJob::class,
            CleanupAutomationLogsJob::class,
            GenerateBillingReportJob::class,
            GenerateSystemHealthReportJob::class,
        ];
    }

    public function retryDue(?int $limit = null): int
    {
        $limit ??= (int) config('corepanel.automation.scheduler.retry_limit', 25);
        $limit = max(1, $limit);

        if (! $this->failedJobsTableExists()) {
            return 0;
        }

        $allowed = $this->allowedJobClasses();
        $retried = 0;

        $rows = DB::table('failed_jobs')
            ->orderBy('id')
            ->limit($limit * 5)
            ->get(['id', 'uuid', 'payload']);

        foreach ($rows as $row) {
            if ($retried >= $limit) {
                break;
            }

            $class = $this->extractJobClass((string) $row->payload);

            if ($class === null || ! in_array($class, $allowed, true)) {
                continue;
            }

            try {
                Artisan::call('queue:retry', ['id' => [(string) $row->uuid]]);
                $retried++;
            } catch (Throwable $e) {
                Log::warning('automation.retry.failed_job', [
                    'uuid' => $row->uuid,
                    'job' => $class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($retried > 0) {
            Log::info('automation.retry.system_jobs', ['retried' => $retried]);
        }

        return $retried;
    }

    private function failedJobsTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('failed_jobs');
        } catch (Throwable) {
            return false;
        }
    }

    private function extractJobClass(string $payload): ?string
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $command = $decoded['data']['command'] ?? null;

        if (! is_string($command) || $command === '') {
            $display = $decoded['displayName'] ?? null;

            return is_string($display) && $display !== '' ? $display : null;
        }

        if (preg_match('/O:\d+:"([^"]+)"/', $command, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
