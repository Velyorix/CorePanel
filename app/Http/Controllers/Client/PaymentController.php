<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientPaymentRequest;
use Core\Auth\Models\User;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Models\Payment;
use Core\Billing\Services\PaymentService;
use Core\Clients\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
    }

    public function index(IndexClientPaymentRequest $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null) {
            return view('client.payments.index', [
                'payments' => null,
                'filters' => $request->filters(),
                'statuses' => PaymentStatus::cases(),
                'clientMissing' => true,
            ]);
        }

        $filters = $request->filters();

        return view('client.payments.index', [
            'payments' => $this->payments->paginateForClient($client, $filters),
            'filters' => $filters,
            'statuses' => PaymentStatus::cases(),
            'clientMissing' => false,
        ]);
    }

    public function show(Request $request, Payment $payment): View
    {
        abort_unless($request->user()?->can('client.invoices.pay') ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $payment->client_id === $client->id,
            404,
        );

        $payment->load(['invoice']);

        return view('client.payments.show', [
            'payment' => $payment,
        ]);
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
