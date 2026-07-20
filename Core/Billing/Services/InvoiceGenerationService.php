<?php

namespace Core\Billing\Services;

use Core\Auth\Models\User;
use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\DataTransferObjects\TaxLineInput;
use Core\Billing\DataTransferObjects\RenewalInvoiceInput;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generates draft invoices from paid orders and service renewals.
 * Does not assign invoice numbers.
 */
class InvoiceGenerationService
{
    public function __construct(
        private readonly TaxCalculationService $taxCalculation,
    ) {
    }

    /**
     * Snapshot a paid order into a draft invoice.
     * Idempotent: returns the existing invoice when order_id is already invoiced.
     */
    public function createFromOrder(Order $order, ?User $createdBy = null): Invoice
    {
        $existing = $this->findForOrder($order);

        if ($existing !== null) {
            return $existing;
        }

        if ($order->status !== OrderStatus::Paid) {
            throw new RuntimeException('Only paid orders can be converted into invoices.');
        }

        $order->loadMissing(['items', 'client']);

        if ($order->items->isEmpty()) {
            throw new InvalidArgumentException('Cannot generate an invoice from an order without line items.');
        }

        return DB::transaction(function () use ($order, $createdBy): Invoice {
            $address = TaxAddress::fromOrder($order);

            $grossSubtotal = $this->money(
                $order->items->sum(fn (OrderItem $item): float => (float) $item->line_total),
            );
            $discount = $this->money((float) ($order->discount_amount ?? 0));
            $taxable = $this->money(max(0, (float) $grossSubtotal - (float) $discount));

            $tax = $this->taxCalculation->calculateDocument(
                [new TaxLineInput($taxable)],
                $address,
            );

            $invoice = Invoice::query()->create([
                'invoice_number' => null,
                'client_id' => $order->client_id,
                'order_id' => $order->id,
                'coupon_id' => $order->coupon_id,
                'created_by' => $createdBy?->id ?? $order->created_by,
                'status' => InvoiceStatus::Draft,
                'currency' => $order->currency ?? 'EUR',
                'contact_name' => $order->contact_name,
                'contact_email' => $order->contact_email,
                'company_name' => $order->company_name,
                'vat_number' => $order->vat_number,
                'address' => $order->address,
                'city' => $order->city,
                'country' => $order->country,
                'postal_code' => $order->postal_code,
                'phone' => $order->phone,
                'notes' => $order->notes,
                'subtotal' => $grossSubtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax->taxAmount,
                'total_amount' => $this->money((float) $taxable + (float) $tax->taxAmount),
                'issued_at' => null,
                'due_at' => null,
                'paid_at' => null,
                'cancelled_at' => null,
            ]);

            $grossFloat = (float) $grossSubtotal;
            $taxFloat = (float) $tax->taxAmount;
            $allocated = 0.0;
            $items = $order->items->values();
            $lastIndex = $items->count() - 1;

            foreach ($items as $index => $item) {
                if ($grossFloat <= 0 || $taxFloat <= 0) {
                    $lineTax = '0.00';
                } elseif ($index === $lastIndex) {
                    $lineTax = $this->money($taxFloat - $allocated);
                } else {
                    $share = ((float) $item->line_total / $grossFloat) * $taxFloat;
                    $lineTax = $this->money($share);
                    $allocated += (float) $lineTax;
                }

                $this->createInvoiceItemFromOrder($invoice, $item, $lineTax);
            }

            return $invoice->fresh(['items', 'client', 'order']) ?? $invoice;
        });
    }

    /**
     * Create a draft renewal invoice for a service period.
     * Idempotent: returns the existing open renewal invoice for the same service_id.
     */
    public function createRenewal(RenewalInvoiceInput $input, ?User $createdBy = null): Invoice
    {
        if ($input->serviceId < 1) {
            throw new InvalidArgumentException('A valid service_id is required for renewal invoices.');
        }

        $existing = $this->findOpenRenewalForService($input->serviceId);

        if ($existing !== null) {
            return $existing;
        }

        return $this->createOneLineServiceInvoice($input, $createdBy);
    }

    /**
     * Create a draft one-line charge invoice for a service (upgrade prorata, etc.).
     * Not idempotent — each call creates a new draft.
     */
    public function createServiceCharge(RenewalInvoiceInput $input, ?User $createdBy = null): Invoice
    {
        if ($input->serviceId < 1) {
            throw new InvalidArgumentException('A valid service_id is required for service charge invoices.');
        }

        return $this->createOneLineServiceInvoice($input, $createdBy);
    }

    /**
     * @return Invoice
     */
    private function createOneLineServiceInvoice(RenewalInvoiceInput $input, ?User $createdBy = null): Invoice
    {
        $client = Client::query()->find($input->clientId);

        if ($client === null) {
            throw new InvalidArgumentException('Cannot generate a service invoice for an unknown client.');
        }

        $contactName = $input->contactName;
        $contactEmail = $input->contactEmail;

        if ($contactName === null || $contactEmail === null) {
            $client->loadMissing('owner');
            $contactName ??= $client->owner?->name;
            $contactEmail ??= $client->owner?->email;
        }

        if ($contactName === null || $contactEmail === null) {
            throw new InvalidArgumentException('Contact name and email are required for service invoices.');
        }

        $lineTotal = $input->line->lineTotal();

        return DB::transaction(function () use ($input, $createdBy, $contactName, $contactEmail, $lineTotal): Invoice {
            $tax = $this->taxCalculation->calculateDocument(
                [new TaxLineInput($lineTotal)],
                $input->taxAddress(),
            );

            $invoice = Invoice::query()->create([
                'invoice_number' => null,
                'client_id' => $input->clientId,
                'order_id' => null,
                'created_by' => $createdBy?->id ?? $input->createdBy,
                'status' => InvoiceStatus::Draft,
                'currency' => $input->currency !== '' ? $input->currency : 'EUR',
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
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
                'issued_at' => null,
                'due_at' => $input->billingPeriodEnd,
                'paid_at' => null,
                'cancelled_at' => null,
            ]);

            $line = $input->line;
            $description = filled($line->description)
                ? $line->description
                : (filled($line->productName)
                    ? (string) $line->productName
                    : __('Service #:id charge', ['id' => $input->serviceId]));

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'product_id' => $line->productId,
                'service_id' => $input->serviceId,
                'description' => $description,
                'product_name' => $line->productName,
                'product_slug' => $line->productSlug,
                'billing_cycle' => $line->billingCycle,
                'custom_interval_days' => $line->customIntervalDays,
                'quantity' => max(1, $line->quantity),
                'options' => $line->options,
                'addons' => $line->addons,
                'config_data' => $line->configData,
                'unit_price' => $this->money((float) $line->unitPrice),
                'setup_fee' => '0.00',
                'tax_amount' => $tax->lines[0]->taxAmount,
                'line_total' => $lineTotal,
            ]);

            return $invoice->fresh(['items', 'client']) ?? $invoice;
        });
    }

    public function findForOrder(Order $order): ?Invoice
    {
        return Invoice::query()
            ->with(['items', 'client', 'order'])
            ->where('order_id', $order->id)
            ->first();
    }

    /**
     * Open renewal invoice for a service (draft, unpaid, or overdue).
     */
    public function findOpenRenewalForService(int $serviceId): ?Invoice
    {
        return Invoice::query()
            ->with(['items', 'client'])
            ->whereIn('status', [
                InvoiceStatus::Draft,
                InvoiceStatus::Unpaid,
                InvoiceStatus::Overdue,
            ])
            ->whereHas('items', function ($query) use ($serviceId): void {
                $query->where('service_id', $serviceId);
            })
            ->orderBy('id')
            ->first();
    }

    private function createInvoiceItemFromOrder(Invoice $invoice, OrderItem $item, string $taxAmount): InvoiceItem
    {
        $description = filled($item->product_name)
            ? (string) $item->product_name
            : __('Product #:id', ['id' => $item->product_id]);

        return InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $item->product_id,
            'service_id' => null,
            'description' => $description,
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
            'tax_amount' => $taxAmount,
            'line_total' => $item->line_total,
        ]);
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
