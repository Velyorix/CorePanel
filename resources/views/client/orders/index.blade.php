@php
    $clientStatuses = $statuses;
@endphp

<x-layout.client
    :title="__('Orders')"
    :page-heading="__('Your orders')"
>
    <x-slot:subtitle>
        {{ __('Track order status and review past purchases.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            <x-ui.theme-toggle />
            @auth
                <span class="hidden text-body-sm text-muted-foreground sm:inline">
                    {{ auth()->user()->email }}
                </span>
            @endauth
        </div>
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Client'), 'url' => route('client.dashboard')],
            ['label' => __('Orders')],
        ]" />
    </x-slot:breadcrumbs>

    @if ($clientMissing)
        <x-ui.empty
            :title="__('No client account yet')"
            :description="__('Place an order from the catalog to start tracking your purchases.')"
        >
            <x-slot:actions>
                <x-ui.button :href="route('client.catalog.index')" variant="primary">
                    {{ __('Browse catalog') }}
                </x-ui.button>
            </x-slot:actions>
        </x-ui.empty>
    @else
        <x-ui.table :paginator="$orders">
            <x-slot:filters>
                <form method="GET" action="{{ route('client.orders.index') }}" class="flex w-full flex-wrap items-end gap-3">
                    <div class="min-w-40">
                        <x-ui.select name="status" :label="__('Status')">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($clientStatuses as $status)
                                <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    @if (filled(request('sort')))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if (filled(request('dir')))
                        <input type="hidden" name="dir" value="{{ request('dir') }}">
                    @endif

                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Apply') }}
                    </x-ui.button>

                    @if ($filters['status'] !== null)
                        <x-ui.button :href="route('client.orders.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                            {{ __('Clear') }}
                        </x-ui.button>
                    @endif
                </form>
            </x-slot:filters>

            <x-slot:head>
                <tr>
                    <x-ui.table-heading sort="order_number">{{ __('Order') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="total_amount">{{ __('Total') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="placed_at">{{ __('Placed') }}</x-ui.table-heading>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </x-slot:head>

            <x-slot:empty>
                <tr>
                    <td colspan="5" class="p-4">
                        <x-ui.empty
                            :title="__('No orders yet')"
                            :description="$filters['status'] !== null
                                ? __('Try another status filter.')
                                : __('Orders placed from checkout will appear here.')"
                        >
                            @if ($filters['status'] === null)
                                <x-slot:actions>
                                    <x-ui.button :href="route('client.catalog.index')" variant="primary">
                                        {{ __('Browse catalog') }}
                                    </x-ui.button>
                                </x-slot:actions>
                            @endif
                        </x-ui.empty>
                    </td>
                </tr>
            </x-slot:empty>

            @foreach ($orders as $order)
                @php
                    $statusVariant = match ($order->status) {
                        \Core\Orders\Enums\OrderStatus::Paid => 'success',
                        \Core\Orders\Enums\OrderStatus::PendingPayment => 'warning',
                        \Core\Orders\Enums\OrderStatus::Cancelled => 'danger',
                        default => 'neutral',
                    };
                @endphp
                <tr class="hover:bg-muted/40">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $order->order_number ?: __('Order #:id', ['id' => $order->id]) }}</div>
                        <div class="text-small text-muted-foreground">#{{ $order->id }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$statusVariant">
                            {{ $order->status->label() }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 tabular-nums">
                        {{ number_format((float) $order->total_amount, 2, '.', ' ') }}
                        <span class="text-muted-foreground">{{ $order->currency }}</span>
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                        {{ ($order->placed_at ?? $order->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </td>
                    <td class="px-4 py-3 text-end">
                        <x-ui.button :href="route('client.orders.show', $order)" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.client>
