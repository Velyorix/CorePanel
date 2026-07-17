@php
    $statusVariant = match ($order->status) {
        \Core\Orders\Enums\OrderStatus::Paid => 'success',
        \Core\Orders\Enums\OrderStatus::PendingPayment => 'warning',
        \Core\Orders\Enums\OrderStatus::Cancelled => 'danger',
        default => 'neutral',
    };

    $heading = $order->order_number ?: __('Order #:id', ['id' => $order->id]);
@endphp

<x-layout.client
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Order details and status tracking.') }}
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
            ['label' => __('Orders'), 'url' => route('client.orders.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6">
        <x-ui.button :href="route('client.orders.index')" variant="secondary" size="sm">
            {{ __('Back to orders') }}
        </x-ui.button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('Order status')">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <span class="text-body-sm text-muted-foreground">{{ __('Current status') }}</span>
                    <x-ui.badge :variant="$statusVariant">{{ $order->status->label() }}</x-ui.badge>
                </div>

                <ol class="space-y-4">
                    @foreach ($timeline as $step)
                        <li class="flex gap-3">
                            <span @class([
                                'mt-1 size-2.5 shrink-0 rounded-full',
                                'bg-success-600' => $step['done'] && ! $step['current'],
                                'bg-warning-600' => $step['current'],
                                'bg-muted-foreground/40' => ! $step['done'] && ! $step['current'],
                            ])></span>
                            <div class="min-w-0 flex-1">
                                <div @class([
                                    'text-body-sm font-medium',
                                    'text-muted-foreground' => ! $step['done'] && ! $step['current'],
                                ])>
                                    {{ $step['label'] }}
                                </div>
                                <div class="text-small text-muted-foreground">
                                    @if ($step['at'])
                                        {{ $step['at']->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                    @elseif ($step['current'])
                                        {{ __('In progress') }}
                                    @else
                                        {{ __('Pending') }}
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <dl class="mt-6 space-y-3 border-t border-border pt-4 text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Payment method') }}</dt>
                        <dd>{{ $order->payment_method ?: '—' }}</dd>
                    </div>
                    @if ($order->coupon_code)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Coupon') }}</dt>
                            <dd>{{ $order->coupon_code }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Items')">
                <ul class="space-y-4">
                    @foreach ($order->items as $item)
                        <li class="border-b border-border pb-4 last:border-0 last:pb-0">
                            <div class="flex justify-between gap-4 text-body-sm font-medium">
                                <span>{{ $item->product_name ?: ($item->product?->name ?? __('Product')) }}</span>
                                <span class="tabular-nums">{{ number_format((float) $item->line_total, 2, '.', ' ') }}</span>
                            </div>
                            <p class="mt-1 text-small text-muted-foreground">
                                {{ $item->billing_cycle?->label() ?? '—' }}
                                · {{ __('Qty') }} {{ $item->quantity }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('Billing details')">
                <dl class="space-y-3 text-body-sm">
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
                </dl>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @include('client.partials.order-summary', ['summary' => $summary])
        </div>
    </div>
</x-layout.client>
