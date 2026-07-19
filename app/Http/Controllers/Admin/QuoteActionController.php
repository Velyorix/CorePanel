<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Auth\Models\User;
use Core\Billing\Models\Quote;
use Core\Billing\Services\BillingDocumentPdfService;
use Core\Billing\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class QuoteActionController extends Controller
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly BillingDocumentPdfService $pdfs,
    ) {
    }

    public function send(Quote $quote): RedirectResponse
    {
        return $this->runTransition($quote, 'send', __('Quote sent to the client.'));
    }

    public function convert(Request $request, Quote $quote): RedirectResponse
    {
        Gate::authorize('manage', $quote);

        $creator = $request->user();

        try {
            $invoice = $this->quotes->convertToInvoice(
                $quote,
                $creator instanceof User ? $creator : null,
            );
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['quote' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', __('Quote converted to an invoice successfully.'));
    }

    public function cancel(Quote $quote): RedirectResponse
    {
        return $this->runTransition($quote, 'cancel', __('Quote cancelled successfully.'));
    }

    public function pdf(Quote $quote): Response
    {
        Gate::authorize('view', $quote);

        return $this->pdfs->downloadQuote($quote);
    }

    private function runTransition(Quote $quote, string $action, string $successMessage): RedirectResponse
    {
        Gate::authorize('manage', $quote);

        try {
            $this->quotes->{$action}($quote);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withErrors(['quote' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.quotes.show', $quote)
            ->with('status', $successMessage);
    }
}
