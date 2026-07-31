<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Orders\Models\Order;
use Core\Orders\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class OrderStatusController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function markPendingPayment(Order $order): RedirectResponse
    {
        return $this->runTransition($order, 'markPendingPayment', __('Order marked as pending payment.'));
    }

    public function markPaid(Order $order): RedirectResponse
    {
        return $this->runTransition($order, 'markPaid', __('Order marked as paid.'));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('manage', $order);

        $reason = filled($request->input('reason'))
            ? trim((string) $request->input('reason'))
            : null;

        try {
            $this->orderService->cancel($order, $reason);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', __('Order cancelled successfully.'));
    }

    private function runTransition(Order $order, string $action, string $successMessage): RedirectResponse
    {
        Gate::authorize('manage', $order);

        try {
            $this->orderService->{$action}($order);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', $successMessage);
    }
}
