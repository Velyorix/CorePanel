<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexOrderRequest;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Services\OrderService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function index(IndexOrderRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.orders.index', [
            'orders' => $this->orderService->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => OrderStatus::cases(),
            'sources' => OrderSource::cases(),
        ]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['client.owner', 'items.product', 'creator', 'cart']);

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }
}
