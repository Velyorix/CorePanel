<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexPaymentRequest;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Models\Payment;
use Core\Billing\Services\PaymentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
    }

    public function index(IndexPaymentRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.payments.index', [
            'payments' => $this->payments->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => PaymentStatus::cases(),
        ]);
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['invoice', 'client.owner']);

        return view('admin.payments.show', [
            'payment' => $payment,
        ]);
    }
}
