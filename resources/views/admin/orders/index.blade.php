<x-layout.admin
    :title="__('Orders')"
    :page-heading="__('Orders')"
>
    <x-slot:subtitle>
        {{ __('Review customer orders and apply manual status actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Orders')],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$orders">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.orders.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Order #, contact, client…')"
                    />
                </div>

                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-40">
                    <x-ui.select name="source" :label="__('Source')">
                        <option value="">{{ __('All sources') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}" @selected(($filters['source']?->value ?? null) === $source->value)>
                                {{ $source->label() }}
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

                @if (filled($filters['q']) || $filters['status'] !== null || $filters['source'] !== null)
                    <x-ui.button :href="route('admin.orders.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="order_number">{{ __('Order') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Client') }}</th>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <x-ui.table-heading sort="source">{{ __('Source') }}</x-ui.table-heading>
                <x-ui.table-heading sort="total_amount">{{ __('Total') }}</x-ui.table-heading>
                <x-ui.table-heading sort="created_at">{{ __('Created') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="7" class="p-4">
                    <x-ui.empty
                        :title="__('No orders found')"
                        :description="filled($filters['q']) || $filters['status'] !== null || $filters['source'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Orders will appear here after checkout or admin creation.')"
                    />
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
                    <div class="font-medium">{{ $order->order_number ?: __('Draft #'.$order->id) }}</div>
                    <div class="text-small text-muted-foreground">#{{ $order->id }}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $order->client?->company_name ?: __('Client #'.($order->client_id ?? '—')) }}</div>
                    <div class="text-small text-muted-foreground">{{ $order->contact_email }}</div>
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$statusVariant">
                        {{ $order->status->label() }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $order->source->label() }}</td>
                <td class="px-4 py-3 tabular-nums">
                    {{ number_format((float) $order->total_amount, 2, '.', ' ') }}
                    <span class="text-muted-foreground">{{ $order->currency }}</span>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.orders.show', $order)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
