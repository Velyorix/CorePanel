<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientInvoiceRequest;
use Core\Auth\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\BillingDocumentPdfService;
use Core\Billing\Services\InvoiceService;
use Core\Clients\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly BillingDocumentPdfService $pdfs,
    ) {
    }

    public function index(IndexClientInvoiceRequest $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null) {
            return view('client.invoices.index', [
                'invoices' => null,
                'filters' => $request->filters(),
                'statuses' => $this->visibleStatuses(),
                'clientMissing' => true,
            ]);
        }

        $filters = $request->filters();

        return view('client.invoices.index', [
            'invoices' => $this->invoices->paginateForClient($client, $filters),
            'filters' => $filters,
            'statuses' => $this->visibleStatuses(),
            'clientMissing' => false,
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);

        $invoice->load(['items', 'payments']);

        return view('client.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorizeInvoice($request, $invoice);

        return $this->pdfs->downloadInvoice($invoice);
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($request->user()?->can('client.invoices.view') ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $invoice->client_id === $client->id,
            404,
        );

        abort_if($invoice->status === InvoiceStatus::Draft, 404);
    }

    /**
     * @return list<InvoiceStatus>
     */
    private function visibleStatuses(): array
    {
        return array_values(array_filter(
            InvoiceStatus::cases(),
            fn (InvoiceStatus $status): bool => $status !== InvoiceStatus::Draft,
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
