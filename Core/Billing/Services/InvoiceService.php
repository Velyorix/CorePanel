<?php

namespace Core\Billing\Services;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Events\InvoiceIssued;
use Core\Billing\Models\Invoice;
use Core\Clients\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Invoice listing and issue (draft → unpaid with number).
 */
class InvoiceService
{
    public function __construct(
        private readonly InvoiceNumberService $numbers,
        private readonly BillingAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: InvoiceStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'invoice_number',
            'status',
            'total_amount',
            'issued_at',
            'due_at',
            'created_at',
        ], true)) {
            $sort = 'created_at';
        }

        $query = Invoice::query()->with(['client', 'items']);

        if ($status instanceof InvoiceStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('invoice_number', 'like', $term)
                    ->orWhere('contact_name', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhereHas('client', function ($clientQuery) use ($term): void {
                        $clientQuery->where('company_name', 'like', $term);
                    });
            });
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     status?: InvoiceStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForClient(Client $client, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'issued_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'invoice_number',
            'status',
            'total_amount',
            'issued_at',
            'due_at',
            'created_at',
        ], true)) {
            $sort = 'issued_at';
        }

        $query = Invoice::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->with(['items']);

        if ($status instanceof InvoiceStatus) {
            if ($status === InvoiceStatus::Draft) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('status', $status->value);
            }
        }

        return $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Issue a draft invoice: assign number, set unpaid, issued/due dates.
     */
    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== InvoiceStatus::Draft) {
                throw new RuntimeException('Only draft invoices can be issued.');
            }

            if ($locked->items()->count() < 1) {
                throw new InvalidArgumentException('Cannot issue an invoice without line items.');
            }

            $before = $this->auditLogger->invoiceSnapshot($locked);

            $numbered = $this->numbers->assignNumber($locked);

            $dueDays = max(0, (int) config('corepanel.billing.invoice_due_days', 14));

            $numbered->forceFill([
                'status' => InvoiceStatus::Unpaid,
                'issued_at' => $numbered->issued_at ?? now(),
                'due_at' => $numbered->due_at ?? now()->addDays($dueDays),
            ])->save();

            $fresh = $numbered->fresh(['items', 'client']) ?? $numbered;

            $this->auditLogger->log(
                BillingAuditLogger::ACTION_INVOICE_ISSUED,
                Invoice::class,
                $fresh->id,
                $before,
                $this->auditLogger->invoiceSnapshot($fresh),
            );

            event(new InvoiceIssued($fresh));

            return $fresh;
        });
    }
}
