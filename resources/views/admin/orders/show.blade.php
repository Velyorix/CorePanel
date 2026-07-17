@php
    $statusVariant = match ($order->status) {
        \Core\Orders\Enums\OrderStatus::Paid => 'success',
        \Core\Orders\Enums\OrderStatus::PendingPayment => 'warning',
        \Core\Orders\Enums\OrderStatus::Cancelled => 'danger',
        default => 'neutral',
    };

    $heading = $order->order_number ?: __('Draft order #'.$order->id);
@endphp

<x-layout.admin
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Order details, line items, and manual lifecycle actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Orders'), 'url' => route('admin.orders.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('manage', $order)
            @if ($order->status === \Core\Orders\Enums\OrderStatus::Draft)
                <form method="POST" action="{{ route('admin.orders.mark-pending-payment', $order) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Mark pending payment') }}
                    </x-ui.button>
                </form>
            @endif

            @if ($order->status === \Core\Orders\Enums\OrderStatus::PendingPayment)
                <form method="POST" action="{{ route('admin.orders.mark-paid', $order) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Mark paid') }}
                    </x-ui.button>
                </form>
            @endif

            @if ($order->status->canTransitionTo(\Core\Orders\Enums\OrderStatus::Cancelled))
                <form
                    method="POST"
                    action="{{ route('admin.orders.cancel', $order) }}"
                    class="flex flex-wrap items-end gap-2"
                    onsubmit="return confirm(@js(__('Cancel this order?')))"
                >
                    @csrf
                    <div class="min-w-48">
                        <x-ui.input
                            name="reason"
                            :label="__('Cancel reason')"
                            :value="old('reason')"
                            :placeholder="__('Optional note…')"
                        />
                    </div>
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Cancel order') }}
                    </x-ui.button>
                </form>
            @endif
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('status'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('status') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$statusVariant">{{ $order->status->label() }}</x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Source') }}</dt>
                    <dd>{{ $order->source->label() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Payment method') }}</dt>
                    <dd>{{ $order->payment_method ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Currency') }}</dt>
                    <dd>{{ $order->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Created') }}</dt>
                    <dd>{{ $order->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Placed') }}</dt>
                    <dd>{{ $order->placed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Paid') }}</dt>
                    <dd>{{ $order->paid_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Cancelled') }}</dt>
                    <dd>{{ $order->cancelled_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                @if ($order->creator)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Created by') }}</dt>
                        <dd>{{ $order->creator->name }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Client & billing')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Client') }}</dt>
                    <dd>
                        @if ($order->client)
                            <a href="{{ route('admin.clients.show', $order->client) }}" class="text-primary hover:underline">
                                {{ $order->client->company_name ?: __('Client #'.$order->client->id) }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Contact') }}</dt>
                    <dd>{{ $order->contact_name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Email') }}</dt>
                    <dd>{{ $order->contact_email ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Company') }}</dt>
                    <dd>{{ $order->company_name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Phone') }}</dt>
                    <dd>{{ $order->phone ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Address') }}</dt>
                    <dd class="text-end">
                        @if ($order->address)
                            {{ $order->address }}<br>
                            {{ $order->postal_code }} {{ $order->city }}<br>
                            {{ $order->country }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if (filled($order->notes))
                    <div class="border-t border-border pt-3">
                        <dt class="mb-1 text-muted-foreground">{{ __('Notes') }}</dt>
                        <dd class="whitespace-pre-wrap">{{ $order->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>
    </div>

    <div class="mt-6">
        <x-ui.card :title="__('Line items')">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="border-b border-border text-start text-muted-foreground">
                            <th class="px-2 py-2 font-medium">{{ __('Product') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('Billing') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Qty') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Unit') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Setup') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($order->items as $item)
                            <tr class="border-b border-border/60">
                                <td class="px-2 py-3">
                                    <div class="font-medium">{{ $item->product_name ?: ($item->product?->name ?? __('Product #'.$item->product_id)) }}</div>
                                    <div class="text-small text-muted-foreground">{{ $item->product_slug }}</div>
                                </td>
                                <td class="px-2 py-3 text-muted-foreground">
                                    {{ $item->billing_cycle?->label() ?? '—' }}
                                </td>
                                <td class="px-2 py-3 text-end tabular-nums">{{ $item->quantity }}</td>
                                <td class="px-2 py-3 text-end tabular-nums">{{ number_format((float) $item->unit_price, 2, '.', ' ') }}</td>
                                <td class="px-2 py-3 text-end tabular-nums">{{ number_format((float) $item->setup_fee, 2, '.', ' ') }}</td>
                                <td class="px-2 py-3 text-end tabular-nums font-medium">{{ number_format((float) $item->line_total, 2, '.', ' ') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-2 py-6 text-center text-muted-foreground">
                                    {{ __('No line items on this order.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <dl class="mt-4 space-y-2 border-t border-border pt-4 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Recurring subtotal') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $order->subtotal_recurring, 2, '.', ' ') }} {{ $order->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Setup subtotal') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $order->subtotal_setup, 2, '.', ' ') }} {{ $order->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Tax') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $order->tax_amount, 2, '.', ' ') }} {{ $order->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4 text-body font-medium">
                    <dt>{{ __('Total') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $order->total_amount, 2, '.', ' ') }} {{ $order->currency }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layout.admin>
