<?php

namespace Core\Billing\Services;

use Core\Auth\Models\User;
use Core\Billing\DataTransferObjects\CreateQuoteInput;
use Core\Billing\DataTransferObjects\QuoteLineInput;
use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\DataTransferObjects\TaxLineInput;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Exceptions\InvalidQuoteException;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Billing\Models\Quote;
use Core\Billing\Models\QuoteItem;
use Core\Clients\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Quote lifecycle: create, send, accept/decline/cancel, convert to invoice.
 */
class QuoteService
{
    public function __construct(
        private readonly TaxCalculationService $taxCalculation,
        private readonly QuoteNumberService $quoteNumbers,
        private readonly InvoiceNumberService $invoiceNumbers,
    ) {
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: QuoteStatus|null,
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
            'quote_number',
            'status',
            'total_amount',
            'valid_until',
            'sent_at',
            'created_at',
        ], true)) {
            $sort = 'created_at';
        }

        $query = Quote::query()->with(['client', 'items']);

        if ($status instanceof QuoteStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('quote_number', 'like', $term)
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
     *     status?: QuoteStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForClient(Client $client, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'sent_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'quote_number',
            'status',
            'total_amount',
            'valid_until',
            'sent_at',
            'created_at',
        ], true)) {
            $sort = 'sent_at';
        }

        $query = Quote::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', QuoteStatus::Draft->value)
            ->with(['items']);

        if ($status instanceof QuoteStatus) {
            if ($status === QuoteStatus::Draft) {
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

    public function create(CreateQuoteInput $input, ?User $createdBy = null): Quote
    {
        $this->assertHasLines($input->lines);
        $this->assertClientExists($input->clientId);

        return DB::transaction(function () use ($input, $createdBy): Quote {
            [$tax, $preparedLines] = $this->calculateLines($input->lines, $input->taxAddress());

            $quote = Quote::query()->create([
                'quote_number' => null,
                'client_id' => $input->clientId,
                'created_by' => $createdBy?->id,
                'converted_invoice_id' => null,
                'status' => QuoteStatus::Draft,
                'currency' => $input->currency !== '' ? $input->currency : 'EUR',
                'contact_name' => $input->contactName,
                'contact_email' => $input->contactEmail,
                'company_name' => $input->companyName,
                'vat_number' => $input->vatNumber,
                'address' => $input->address,
                'city' => $input->city,
                'country' => $input->country,
                'postal_code' => $input->postalCode,
                'phone' => $input->phone,
                'notes' => $input->notes,
                'subtotal' => $tax->subtotal,
                'tax_amount' => $tax->taxAmount,
                'total_amount' => $tax->total,
                'valid_until' => $input->validUntil,
                'sent_at' => null,
                'accepted_at' => null,
                'converted_at' => null,
            ]);

            $this->persistItems($quote, $preparedLines, $tax->lines);

            return $quote->fresh(['items', 'client']) ?? $quote;
        });
    }

    public function update(Quote $quote, CreateQuoteInput $input): Quote
    {
        if (! $quote->isEditable()) {
            throw new InvalidQuoteException('Only draft quotes can be updated.');
        }

        $this->assertHasLines($input->lines);

        if ($input->clientId !== $quote->client_id) {
            throw new InvalidQuoteException('Cannot change the client on an existing quote.');
        }

        return DB::transaction(function () use ($quote, $input): Quote {
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw new InvalidQuoteException('Only draft quotes can be updated.');
            }

            [$tax, $preparedLines] = $this->calculateLines($input->lines, $input->taxAddress());

            $locked->items()->delete();

            $locked->forceFill([
                'currency' => $input->currency !== '' ? $input->currency : 'EUR',
                'contact_name' => $input->contactName,
                'contact_email' => $input->contactEmail,
                'company_name' => $input->companyName,
                'vat_number' => $input->vatNumber,
                'address' => $input->address,
                'city' => $input->city,
                'country' => $input->country,
                'postal_code' => $input->postalCode,
                'phone' => $input->phone,
                'notes' => $input->notes,
                'subtotal' => $tax->subtotal,
                'tax_amount' => $tax->taxAmount,
                'total_amount' => $tax->total,
                'valid_until' => $input->validUntil,
            ])->save();

            $this->persistItems($locked, $preparedLines, $tax->lines);

            return $locked->fresh(['items', 'client']) ?? $locked;
        });
    }

    public function send(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $locked = $this->markExpiredIfNeeded($locked);

            if ($locked->status !== QuoteStatus::Draft) {
                throw new InvalidQuoteException('Only draft quotes can be sent.');
            }

            $locked->loadMissing('items');

            if ($locked->items->isEmpty()) {
                throw new InvalidQuoteException('Cannot send a quote without line items.');
            }

            $numbered = $this->quoteNumbers->assignNumber($locked);

            $validUntil = $numbered->valid_until ?? now()->addDays(
                max(1, (int) config('corepanel.billing.quote_valid_days', 30)),
            );

            $numbered->forceFill([
                'status' => QuoteStatus::Sent,
                'sent_at' => now(),
                'valid_until' => $validUntil,
            ])->save();

            return $numbered->fresh(['items', 'client']) ?? $numbered;
        });
    }

    public function accept(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $locked = $this->markExpiredIfNeeded($locked);

            if ($locked->status !== QuoteStatus::Sent) {
                throw new InvalidQuoteException('Only sent quotes can be accepted.');
            }

            $locked->forceFill([
                'status' => QuoteStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            return $locked->fresh(['items', 'client']) ?? $locked;
        });
    }

    public function decline(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $locked = $this->markExpiredIfNeeded($locked);

            if ($locked->status !== QuoteStatus::Sent) {
                throw new InvalidQuoteException('Only sent quotes can be declined.');
            }

            $locked->forceFill([
                'status' => QuoteStatus::Declined,
            ])->save();

            return $locked->fresh(['items', 'client']) ?? $locked;
        });
    }

    public function cancel(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [QuoteStatus::Draft, QuoteStatus::Sent], true)) {
                throw new InvalidQuoteException('Only draft or sent quotes can be cancelled.');
            }

            $locked->forceFill([
                'status' => QuoteStatus::Cancelled,
            ])->save();

            return $locked->fresh(['items', 'client']) ?? $locked;
        });
    }

    /**
     * Convert a sent/accepted quote into an unpaid invoice.
     * Idempotent when already converted.
     */
    public function convertToInvoice(Quote $quote, ?User $createdBy = null): Invoice
    {
        return DB::transaction(function () use ($quote, $createdBy): Invoice {
            $locked = Quote::query()
                ->with('items')
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->converted_invoice_id !== null) {
                return Invoice::query()
                    ->with(['items', 'client'])
                    ->findOrFail($locked->converted_invoice_id);
            }

            $locked = $this->markExpiredIfNeeded($locked);

            if (! $locked->isConvertible()) {
                throw new InvalidQuoteException(
                    'Only sent or accepted quotes that are still valid can be converted.',
                );
            }

            if ($locked->items->isEmpty()) {
                throw new InvalidQuoteException('Cannot convert a quote without line items.');
            }

            $tax = $this->taxCalculation->calculateDocument(
                $locked->items
                    ->map(fn (QuoteItem $item): TaxLineInput => new TaxLineInput(
                        $this->money((float) $item->line_total),
                    ))
                    ->values()
                    ->all(),
                TaxAddress::fromQuote($locked),
            );

            $invoice = Invoice::query()->create([
                'invoice_number' => null,
                'client_id' => $locked->client_id,
                'order_id' => null,
                'created_by' => $createdBy?->id ?? $locked->created_by,
                'status' => InvoiceStatus::Draft,
                'currency' => $locked->currency ?? 'EUR',
                'contact_name' => $locked->contact_name,
                'contact_email' => $locked->contact_email,
                'company_name' => $locked->company_name,
                'vat_number' => $locked->vat_number,
                'address' => $locked->address,
                'city' => $locked->city,
                'country' => $locked->country,
                'postal_code' => $locked->postal_code,
                'phone' => $locked->phone,
                'notes' => $locked->notes,
                'subtotal' => $tax->subtotal,
                'tax_amount' => $tax->taxAmount,
                'total_amount' => $tax->total,
                'issued_at' => null,
                'due_at' => null,
                'paid_at' => null,
                'cancelled_at' => null,
            ]);

            foreach ($locked->items->values() as $index => $item) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'service_id' => null,
                    'description' => $item->description,
                    'product_name' => $item->product_name,
                    'product_slug' => $item->product_slug,
                    'billing_cycle' => $item->billing_cycle,
                    'custom_interval_days' => $item->custom_interval_days,
                    'quantity' => $item->quantity,
                    'options' => $item->options,
                    'addons' => $item->addons,
                    'config_data' => $item->config_data,
                    'unit_price' => $item->unit_price,
                    'setup_fee' => $item->setup_fee,
                    'tax_amount' => $tax->lines[$index]->taxAmount,
                    'line_total' => $item->line_total,
                ]);
            }

            $invoice = $this->invoiceNumbers->assignNumber($invoice);

            $dueDays = max(1, (int) config('corepanel.billing.invoice_due_days', 14));

            $invoice->forceFill([
                'status' => InvoiceStatus::Unpaid,
                'issued_at' => now(),
                'due_at' => now()->addDays($dueDays),
            ])->save();

            $locked->forceFill([
                'status' => QuoteStatus::Converted,
                'converted_at' => now(),
                'converted_invoice_id' => $invoice->id,
                'accepted_at' => $locked->accepted_at ?? now(),
            ])->save();

            return $invoice->fresh(['items', 'client']) ?? $invoice;
        });
    }

    public function findConvertedInvoice(Quote $quote): ?Invoice
    {
        if ($quote->converted_invoice_id === null) {
            return null;
        }

        return Invoice::query()
            ->with(['items', 'client'])
            ->find($quote->converted_invoice_id);
    }

    public function markExpiredIfNeeded(Quote $quote): Quote
    {
        if (
            in_array($quote->status, [QuoteStatus::Sent, QuoteStatus::Accepted], true)
            && $quote->isPastValidUntil()
        ) {
            $quote->forceFill([
                'status' => QuoteStatus::Expired,
            ])->save();

            return $quote->fresh(['items', 'client']) ?? $quote;
        }

        return $quote;
    }

    /**
     * @param  list<QuoteLineInput>  $lines
     * @return array{0: \Core\Billing\DataTransferObjects\TaxDocumentResult, 1: list<array{line: QuoteLineInput, line_total: string}>}
     */
    private function calculateLines(array $lines, TaxAddress $address): array
    {
        $prepared = [];
        $taxInputs = [];

        foreach ($lines as $line) {
            if (! $line instanceof QuoteLineInput) {
                throw new InvalidQuoteException('Each quote line must be a QuoteLineInput instance.');
            }

            $lineTotal = $line->lineTotal();
            $prepared[] = [
                'line' => $line,
                'line_total' => $lineTotal,
            ];
            $taxInputs[] = new TaxLineInput($lineTotal);
        }

        return [$this->taxCalculation->calculateDocument($taxInputs, $address), $prepared];
    }

    /**
     * @param  list<array{line: QuoteLineInput, line_total: string}>  $preparedLines
     * @param  list<\Core\Billing\DataTransferObjects\TaxCalculationResult>  $taxLines
     */
    private function persistItems(Quote $quote, array $preparedLines, array $taxLines): void
    {
        foreach ($preparedLines as $index => $prepared) {
            $line = $prepared['line'];

            QuoteItem::query()->create([
                'quote_id' => $quote->id,
                'product_id' => $line->productId,
                'description' => $line->description,
                'product_name' => $line->productName,
                'product_slug' => $line->productSlug,
                'billing_cycle' => $line->billingCycle,
                'custom_interval_days' => $line->customIntervalDays,
                'quantity' => max(1, $line->quantity),
                'options' => $line->options,
                'addons' => $line->addons,
                'config_data' => $line->configData,
                'unit_price' => $this->money((float) $line->unitPrice),
                'setup_fee' => $this->money((float) $line->setupFee),
                'tax_amount' => $taxLines[$index]->taxAmount,
                'line_total' => $prepared['line_total'],
            ]);
        }
    }

    /**
     * @param  list<QuoteLineInput>  $lines
     */
    private function assertHasLines(array $lines): void
    {
        if ($lines === []) {
            throw new InvalidQuoteException('At least one quote line is required.');
        }
    }

    private function assertClientExists(int $clientId): void
    {
        if (! Client::query()->whereKey($clientId)->exists()) {
            throw new InvalidQuoteException('Cannot create a quote for an unknown client.');
        }
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
