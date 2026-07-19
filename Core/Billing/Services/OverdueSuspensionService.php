<?php

namespace Core\Billing\Services;

use Carbon\CarbonInterface;
use Core\Billing\Contracts\OverdueServiceActions;
use Core\Billing\DataTransferObjects\SuspensionProcessingResult;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\OverdueInvoiceAction;
use Core\Billing\Models\Invoice;
use Core\Clients\Models\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Suspends then terminates services linked to long-overdue invoices.
 * Delegates lifecycle mutations to OverdueServiceActions (null until services engine).
 */
class OverdueSuspensionService
{
    public function __construct(
        private readonly OverdueServiceActions $actions,
        private readonly ClientCreditService $credits,
    ) {
    }

    public function process(?CarbonInterface $asOf = null): SuspensionProcessingResult
    {
        if (! (bool) config('corepanel.billing.suspension.enabled', true)) {
            return new SuspensionProcessingResult;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $suspendAfter = max(1, (int) config('corepanel.billing.suspension.suspend_after_days', 3));
        $terminateAfter = max($suspendAfter, (int) config('corepanel.billing.suspension.terminate_after_days', 7));

        $result = new SuspensionProcessingResult;

        $invoices = Invoice::query()
            ->with(['items', 'client'])
            ->where('status', InvoiceStatus::Overdue)
            ->whereNotNull('due_at')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $outcome = $this->processInvoice($invoice, $asOf, $suspendAfter, $terminateAfter);
                $result = match ($outcome) {
                    'suspended' => $result->withSuspended(),
                    'terminated' => $result->withTerminated(),
                    'skipped' => $result->withSkipped(),
                    default => $result->withSkipped(),
                };
            } catch (Throwable $exception) {
                Log::warning('Overdue suspension processing failed.', [
                    'invoice_id' => $invoice->id,
                    'message' => $exception->getMessage(),
                ]);

                $result = $result->withError();
            }
        }

        return $result;
    }

    /**
     * @return 'suspended'|'terminated'|'skipped'
     */
    private function processInvoice(
        Invoice $invoice,
        CarbonInterface $asOf,
        int $suspendAfter,
        int $terminateAfter,
    ): string {
        if ((float) $invoice->amountDue() <= 0) {
            return 'skipped';
        }

        $daysFromDue = (int) $invoice->due_at->copy()->startOfDay()->diffInDays($asOf, false);

        if ($daysFromDue < $suspendAfter) {
            return 'skipped';
        }

        if ($this->shouldSkipForExceptions($invoice)) {
            return 'skipped';
        }

        $serviceIds = $invoice->items
            ->pluck('service_id')
            ->filter(fn ($id): bool => $id !== null && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($serviceIds === []) {
            return 'skipped';
        }

        $current = $invoice->overdue_action;

        if ($daysFromDue >= $terminateAfter) {
            if ($current === OverdueInvoiceAction::Terminated) {
                return 'skipped';
            }

            $reason = __('Automatic termination after :days days overdue.', [
                'days' => $terminateAfter,
            ]);

            foreach ($serviceIds as $serviceId) {
                $this->actions->terminate($invoice, $serviceId, $reason);
            }

            $invoice->forceFill([
                'overdue_action' => OverdueInvoiceAction::Terminated,
                'overdue_action_at' => now(),
            ])->save();

            return 'terminated';
        }

        if ($current === OverdueInvoiceAction::Suspended || $current === OverdueInvoiceAction::Terminated) {
            return 'skipped';
        }

        $reason = __('Automatic suspension after :days days overdue.', [
            'days' => $suspendAfter,
        ]);

        foreach ($serviceIds as $serviceId) {
            $this->actions->suspend($invoice, $serviceId, $reason);
        }

        $invoice->forceFill([
            'overdue_action' => OverdueInvoiceAction::Suspended,
            'overdue_action_at' => now(),
        ])->save();

        return 'suspended';
    }

    private function shouldSkipForExceptions(Invoice $invoice): bool
    {
        $client = $invoice->client;

        if (! $client instanceof Client) {
            return false;
        }

        if (
            (bool) config('corepanel.billing.suspension.skip_if_credit_available', true)
            && (float) $this->credits->balance($client) > 0
        ) {
            return true;
        }

        if (
            (bool) config('corepanel.billing.suspension.skip_vip_clients', true)
            && $this->clientIsVip($client)
        ) {
            return true;
        }

        return false;
    }

    /**
     * VIP exemption hook. No client VIP column yet — always false until added.
     */
    private function clientIsVip(Client $client): bool
    {
        return (bool) ($client->getAttributes()['is_vip'] ?? false);
    }
}
