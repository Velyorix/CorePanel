<x-layout.client
    :title="__('Order placed')"
    :page-heading="__('Order #:id', ['id' => $order->id])"
>
    <x-slot:subtitle>
        {{ __('Your order is pending payment. Payment processing arrives in a later step.') }}
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
            ['label' => __('Order #:id', ['id' => $order->id])],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('Order status')">
                <dl class="space-y-3 text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                        <dd>{{ $order->status->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Payment method') }}</dt>
                        <dd>{{ $order->payment_method }}</dd>
                    </div>
                    @if ($order->placed_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Placed at') }}</dt>
                            <dd>{{ $order->placed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Items')">
                <ul class="space-y-4">
                    @foreach ($order->items as $item)
                        <li class="border-b border-border pb-4 last:border-0 last:pb-0">
                            <div class="flex justify-between gap-4 text-body-sm font-medium">
                                <span>{{ $item->product?->name ?? __('Product') }}</span>
                                <span>{{ $item->line_total }}</span>
                            </div>
                            <p class="mt-1 text-small text-muted-foreground">
                                {{ $item->billing_cycle->label() }}
                                · {{ __('Qty') }} {{ $item->quantity }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <div class="flex flex-wrap gap-2">
                <x-ui.button :href="route('client.orders.show', $order)" variant="primary">
                    {{ __('View order') }}
                </x-ui.button>
                <x-ui.button :href="route('client.orders.index')" variant="secondary">
                    {{ __('Order history') }}
                </x-ui.button>
                <x-ui.button :href="route('client.catalog.index')" variant="secondary">
                    {{ __('Continue shopping') }}
                </x-ui.button>
            </div>
        </div>

        <div class="space-y-6">
            @include('client.partials.order-summary', ['summary' => $summary])
        </div>
    </div>
</x-layout.client>
