<?php

namespace Core\Billing\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\Quote;
use Illuminate\Http\Response;

/**
 * Renders invoice and quote PDFs from Blade templates (DomPDF).
 */
class BillingDocumentPdfService
{
    public function __construct(
        private readonly BillingSettings $billingSettings,
    ) {
    }

    public function renderInvoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'client']);

        return Pdf::loadView('billing.pdf.invoice', [
            'invoice' => $invoice,
            'seller' => $this->billingSettings->seller(),
            'documentTitle' => __('Invoice'),
            'documentNumber' => $invoice->invoice_number ?: ('#'.$invoice->id),
        ])->output();
    }

    public function renderQuote(Quote $quote): string
    {
        $quote->loadMissing(['items', 'client']);

        return Pdf::loadView('billing.pdf.quote', [
            'quote' => $quote,
            'seller' => $this->billingSettings->seller(),
            'documentTitle' => __('Quote'),
            'documentNumber' => $quote->quote_number ?: ('#'.$quote->id),
        ])->output();
    }

    public function downloadInvoice(Invoice $invoice): Response
    {
        $invoice->loadMissing(['items', 'client']);

        return Pdf::loadView('billing.pdf.invoice', [
            'invoice' => $invoice,
            'seller' => $this->billingSettings->seller(),
            'documentTitle' => __('Invoice'),
            'documentNumber' => $invoice->invoice_number ?: ('#'.$invoice->id),
        ])->download($this->invoiceFilename($invoice));
    }

    public function downloadQuote(Quote $quote): Response
    {
        $quote->loadMissing(['items', 'client']);

        return Pdf::loadView('billing.pdf.quote', [
            'quote' => $quote,
            'seller' => $this->billingSettings->seller(),
            'documentTitle' => __('Quote'),
            'documentNumber' => $quote->quote_number ?: ('#'.$quote->id),
        ])->download($this->quoteFilename($quote));
    }

    public function invoiceFilename(Invoice $invoice): string
    {
        $base = $invoice->invoice_number ?: ('invoice-'.$invoice->id);

        return $this->safeFilename($base).'.pdf';
    }

    public function quoteFilename(Quote $quote): string
    {
        $base = $quote->quote_number ?: ('quote-'.$quote->id);

        return $this->safeFilename($base).'.pdf';
    }

    /**
     * HTML preview of the invoice PDF view (useful in tests).
     */
    public function invoiceHtml(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'client']);

        return view('billing.pdf.invoice', [
            'invoice' => $invoice,
            'seller' => $this->billingSettings->seller(),
            'documentTitle' => __('Invoice'),
            'documentNumber' => $invoice->invoice_number ?: ('#'.$invoice->id),
        ])->render();
    }

    /**
     * HTML preview of the quote PDF view (useful in tests).
     */
    public function quoteHtml(Quote $quote): string
    {
        $quote->loadMissing(['items', 'client']);

        return view('billing.pdf.quote', [
            'quote' => $quote,
            'seller' => $this->billingSettings->seller(),
            'documentTitle' => __('Quote'),
            'documentNumber' => $quote->quote_number ?: ('#'.$quote->id),
        ])->render();
    }

    private function safeFilename(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'document';

        return trim($safe, '-') ?: 'document';
    }
}
