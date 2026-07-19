<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Billing\Models\Payment;
use Core\Billing\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class PaymentActionController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
    }

    public function complete(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('manage', $payment);

        try {
            $this->payments->complete(
                $payment,
                filled($request->input('transaction_id')) ? (string) $request->input('transaction_id') : null,
                filled($request->input('gateway_reference')) ? (string) $request->input('gateway_reference') : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', __('Payment marked as completed.'));
    }

    public function fail(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('manage', $payment);

        try {
            $this->payments->fail(
                $payment,
                filled($request->input('notes')) ? (string) $request->input('notes') : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', __('Payment marked as failed.'));
    }
}
