<?php

namespace Core\Billing\Services;

use Core\Auth\Models\User;
use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\DataTransferObjects\TaxLineInput;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generates draft invoices from paid orders.
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

            $lineInputs = $order->items
                ->map(fn (OrderItem $item): TaxLineInput => new TaxLineInput(
                    $this->money((float) $item->line_total),
                ))
                ->values()
                ->all();

            $tax = $this->taxCalculation->calculateDocument($lineInputs, $address);

            $invoice = Invoice::query()->create([
                'invoice_number' => null,
                'client_id' => $order->client_id,
                'order_id' => $order->id,
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
                'subtotal' => $tax->subtotal,
                'tax_amount' => $tax->taxAmount,
                'total_amount' => $tax->total,
                'issued_at' => null,
                'due_at' => null,
                'paid_at' => null,
                'cancelled_at' => null,
            ]);

            foreach ($order->items->values() as $index => $item) {
                $this->createInvoiceItem($invoice, $item, $tax->lines[$index]->taxAmount);
            }

            return $invoice->fresh(['items', 'client', 'order']) ?? $invoice;
        });
    }

    public function findForOrder(Order $order): ?Invoice
    {
        return Invoice::query()
            ->with(['items', 'client', 'order'])
            ->where('order_id', $order->id)
            ->first();
    }

    private function createInvoiceItem(Invoice $invoice, OrderItem $item, string $taxAmount): InvoiceItem
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
