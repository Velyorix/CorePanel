<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientQuoteRequest;
use Core\Auth\Models\User;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Exceptions\InvalidQuoteException;
use Core\Billing\Models\Quote;
use Core\Billing\Services\BillingDocumentPdfService;
use Core\Billing\Services\QuoteService;
use Core\Clients\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly BillingDocumentPdfService $pdfs,
    ) {
    }

    public function index(IndexClientQuoteRequest $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null) {
            return view('client.quotes.index', [
                'quotes' => null,
                'filters' => $request->filters(),
                'statuses' => $this->visibleStatuses(),
                'clientMissing' => true,
            ]);
        }

        $filters = $request->filters();

        return view('client.quotes.index', [
            'quotes' => $this->quotes->paginateForClient($client, $filters),
            'filters' => $filters,
            'statuses' => $this->visibleStatuses(),
            'clientMissing' => false,
        ]);
    }

    public function show(Request $request, Quote $quote): View
    {
        $this->authorizeQuote($request, $quote);

        $quote->load(['items']);

        return view('client.quotes.show', [
            'quote' => $quote,
        ]);
    }

    public function pdf(Request $request, Quote $quote): Response
    {
        $this->authorizeQuote($request, $quote);

        return $this->pdfs->downloadQuote($quote);
    }

    public function accept(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorizeQuote($request, $quote);

        try {
            $this->quotes->accept($quote);
        } catch (InvalidQuoteException $exception) {
            return back()->withErrors(['quote' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.quotes.show', $quote)
            ->with('status', __('Quote accepted successfully.'));
    }

    public function decline(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorizeQuote($request, $quote);

        try {
            $this->quotes->decline($quote);
        } catch (InvalidQuoteException $exception) {
            return back()->withErrors(['quote' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.quotes.show', $quote)
            ->with('status', __('Quote declined.'));
    }

    private function authorizeQuote(Request $request, Quote $quote): void
    {
        abort_unless($request->user()?->can('client.quotes.view') ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $quote->client_id === $client->id,
            404,
        );

        abort_if($quote->status === QuoteStatus::Draft, 404);
    }

    /**
     * @return list<QuoteStatus>
     */
    private function visibleStatuses(): array
    {
        return array_values(array_filter(
            QuoteStatus::cases(),
            fn (QuoteStatus $status): bool => $status !== QuoteStatus::Draft,
        ));
    }

    private function resolveClient(Request $request): ?Client
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->clients()->orderBy('clients.id')->first()
            ?? $user->ownedClients()->orderBy('id')->first();
    }
}
