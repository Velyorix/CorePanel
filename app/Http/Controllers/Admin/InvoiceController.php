<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexInvoiceRequest;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\InvoiceService;
use Core\Billing\Services\PaymentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly PaymentService $payments,
    ) {
    }

    public function index(IndexInvoiceRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.invoices.index', [
            'invoices' => $this->invoices->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => InvoiceStatus::cases(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['client.owner', 'items.product', 'creator', 'order']);

        return view('admin.invoices.show', [
            'invoice' => $invoice,
            'payments' => $this->payments->forInvoice($invoice),
        ]);
    }
}
