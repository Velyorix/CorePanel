<?php

namespace Core\Billing\Services;

use Carbon\CarbonInterface;
use Core\Billing\Contracts\RenewableBillableSource;
use Core\Billing\DataTransferObjects\RenewalGenerationResult;
use Core\Billing\DataTransferObjects\RenewalInvoiceInput;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates due renewal invoice generation from a billable source.
 */
class RenewalInvoiceService
{
    public function __construct(
        private readonly RenewableBillableSource $billableSource,
        private readonly InvoiceGenerationService $invoiceGeneration,
    ) {
    }

    public function generateDue(?CarbonInterface $asOf = null): RenewalGenerationResult
    {
        if (! (bool) config('corepanel.billing.renewal.enabled', true)) {
            return new RenewalGenerationResult;
        }

        $daysBefore = max(0, (int) config('corepanel.billing.renewal.invoice_days_before', 7));
        $candidates = $this->billableSource->dueForRenewal($asOf ?? now(), $daysBefore);

        $result = new RenewalGenerationResult;

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof RenewalInvoiceInput) {
                $result = $result->withError();

                continue;
            }

            try {
                $existing = $this->invoiceGeneration->findOpenRenewalForService($candidate->serviceId);

                if ($existing !== null) {
                    $result = $result->withSkipped();

                    continue;
                }

                $this->invoiceGeneration->createRenewal($candidate);
                $result = $result->withCreated();
            } catch (Throwable $exception) {
                Log::warning('Renewal invoice generation failed.', [
                    'service_id' => $candidate->serviceId,
                    'client_id' => $candidate->clientId,
                    'message' => $exception->getMessage(),
                ]);

                $result = $result->withError();
            }
        }

        return $result;
    }
}
