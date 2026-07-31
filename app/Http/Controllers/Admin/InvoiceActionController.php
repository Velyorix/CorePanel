<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\BillingDocumentPdfService;
use Core\Billing\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class InvoiceActionController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly BillingDocumentPdfService $pdfs,
    ) {
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manage', $invoice);

        try {
            $this->invoices->issue($invoice);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', __('Invoice issued successfully.'));
    }

    public function pdf(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        return $this->pdfs->downloadInvoice($invoice);
    }
}
