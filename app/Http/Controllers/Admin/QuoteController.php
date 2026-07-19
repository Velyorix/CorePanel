<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexQuoteRequest;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Models\Quote;
use Core\Billing\Services\QuoteService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quotes,
    ) {
    }

    public function index(IndexQuoteRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.quotes.index', [
            'quotes' => $this->quotes->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => QuoteStatus::cases(),
        ]);
    }

    public function show(Quote $quote): View
    {
        Gate::authorize('view', $quote);

        $quote->load(['client.owner', 'items.product', 'creator', 'convertedInvoice']);

        return view('admin.quotes.show', [
            'quote' => $quote,
        ]);
    }
}
