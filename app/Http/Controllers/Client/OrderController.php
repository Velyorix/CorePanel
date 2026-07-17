<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientOrderRequest;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Orders\Enums\OrderStatus;
use Core\Orders\Models\Order;
use Core\Orders\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function index(IndexClientOrderRequest $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null) {
            return view('client.orders.index', [
                'orders' => null,
                'filters' => $request->filters(),
                'statuses' => $this->visibleStatuses(),
                'clientMissing' => true,
            ]);
        }

        $filters = $request->filters();

        return view('client.orders.index', [
            'orders' => $this->orderService->paginateForClient($client, $filters),
            'filters' => $filters,
            'statuses' => $this->visibleStatuses(),
            'clientMissing' => false,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($request->user()?->can('client.orders.view') ?? false, 403);

        $client = $this->resolveClient($request);

        abort_unless(
            $client !== null && $order->client_id === $client->id,
            404,
        );

        abort_if($order->status === OrderStatus::Draft, 404);

        $order->load(['items.product']);

        return view('client.orders.show', [
            'order' => $order,
            'summary' => $this->orderSummary($order),
            'timeline' => $this->statusTimeline($order),
        ]);
    }

    /**
     * @return list<OrderStatus>
     */
    private function visibleStatuses(): array
    {
        return array_values(array_filter(
            OrderStatus::cases(),
            fn (OrderStatus $status): bool => $status !== OrderStatus::Draft,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSummary(Order $order): array
    {
        return [
            'item_count' => $order->items->sum('quantity'),
            'recurring_subtotal' => $order->subtotal_recurring,
            'setup_subtotal' => $order->subtotal_setup,
            'first_payment_subtotal' => number_format(
                (float) $order->subtotal_recurring + (float) $order->subtotal_setup,
                2,
                '.',
                '',
            ),
            'tax_label' => __('Tax'),
            'first_payment_tax' => $order->tax_amount,
            'first_payment_total' => $order->total_amount,
            'tax_is_estimate' => false,
        ];
    }

    /**
     * @return list<array{label: string, at: \Illuminate\Support\Carbon|null, done: bool, current: bool}>
     */
    private function statusTimeline(Order $order): array
    {
        $status = $order->status;

        $timeline = [
            [
                'label' => __('Order placed'),
                'at' => $order->placed_at,
                'done' => $order->placed_at !== null || $status->isPlaced(),
                'current' => false,
            ],
        ];

        if ($status === OrderStatus::Cancelled) {
            $timeline[] = [
                'label' => __('Cancelled'),
                'at' => $order->cancelled_at,
                'done' => true,
                'current' => true,
            ];

            return $timeline;
        }

        $timeline[] = [
            'label' => __('Payment received'),
            'at' => $order->paid_at,
            'done' => $status === OrderStatus::Paid || $order->paid_at !== null,
            'current' => $status === OrderStatus::PendingPayment,
        ];

        return $timeline;
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
