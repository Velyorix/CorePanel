<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Core\Auth\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\PaymentService;
use Core\Clients\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InvoicePaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->can('client.invoices.pay') ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $invoice->client_id === $client->id,
            404,
        );

        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        try {
            $this->payments->initiate($invoice, ManualTransferGateway::KEY);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.invoices.show', $invoice)
            ->with('status', __('Payment initiated. Please follow the provided instructions.'));
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
