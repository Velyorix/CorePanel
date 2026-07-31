<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexOrderRequest;
use App\Http\Requests\Admin\StoreAdminOrderRequest;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderSource;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Services\CheckoutDraftService;
use Core\Orders\Services\OrderService;
use Core\Products\Enums\ProductStatus;
use Core\Products\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CheckoutDraftService $checkoutDraftService,
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

    public function create(Request $request): View
    {
        Gate::authorize('create', Order::class);

        $selectedClient = null;
        $clientId = $request->integer('client_id') ?: null;

        if ($clientId !== null) {
            $selectedClient = Client::query()->with('owner')->find($clientId);
        }

        $draft = $this->checkoutDraftService->prefill(
            $selectedClient,
            $selectedClient?->owner,
        );

        return view('admin.orders.create', [
            'clients' => Client::query()
                ->with('owner')
                ->orderBy('company_name')
                ->orderBy('id')
                ->get(),
            'selectedClient' => $selectedClient,
            'draft' => $draft,
            'paymentMethods' => $this->checkoutDraftService->paymentMethods(),
            'products' => Product::query()
                ->with('pricing')
                ->where('status', ProductStatus::Published->value)
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'cycles' => $product->pricing
                        ->filter(fn ($pricing): bool => (bool) $pricing->is_enabled)
                        ->map(fn ($pricing): array => [
                            'value' => $pricing->billing_cycle->value,
                            'label' => $pricing->billing_cycle->label(),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreAdminOrderRequest $request): RedirectResponse
    {
        $client = Client::query()->findOrFail($request->integer('client_id'));
        $creator = $request->user();

        if (! $creator instanceof User) {
            abort(403);
        }

        try {
            $order = $this->orderService->createFromAdmin(
                $client,
                $creator,
                $request->draftData(),
                $request->cartItems(),
                $request->submitAsPending(),
                filled($request->validated('notes'))
                    ? (string) $request->validated('notes')
                    : null,
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['order' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with(
                'status',
                $request->submitAsPending()
                    ? __('Order created and marked as pending payment.')
                    : __('Draft order created successfully.'),
            );
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
