<x-layout.client
    :title="__('Cart')"
    :page-heading="__('Your cart')"
>
    <x-slot:subtitle>
        {{ __('Review your configuration before checkout.') }}
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
            ['label' => __('Cart')],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to update cart')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    @if ($cart->isEmpty())
        <x-ui.empty
            :title="__('Your cart is empty')"
            :description="__('Browse the catalog to configure a product and add it to your cart.')"
        >
            <x-slot:actions>
                <x-ui.button :href="route('client.catalog.index')" variant="primary">
                    {{ __('Browse catalog') }}
                </x-ui.button>
            </x-slot:actions>
        </x-ui.empty>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                @foreach ($cart->items as $item)
                    <x-ui.card :title="$item->product?->name ?? __('Product')">
                        <dl class="mb-4 space-y-2 text-body-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">{{ __('Billing cycle') }}</dt>
                                <dd>{{ $item->billing_cycle->label() }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">{{ __('Unit price') }}</dt>
                                <dd>{{ $item->unit_price }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">{{ __('Setup fee') }}</dt>
                                <dd>{{ $item->setup_fee }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 font-medium">
                                <dt>{{ __('Line total') }}</dt>
                                <dd>{{ $item->lineSubtotal() }}</dd>
                            </div>
                        </dl>

                        @if (! empty($item->options))
                            <div class="mb-4">
                                <h4 class="mb-2 text-body-sm font-medium">{{ __('Options') }}</h4>
                                <ul class="space-y-1 text-small text-muted-foreground">
                                    @foreach ($item->options as $key => $value)
                                        <li>
                                            <span class="font-medium text-foreground">{{ $key }}</span>:
                                            @if (is_bool($value))
                                                {{ $value ? __('Yes') : __('No') }}
                                            @elseif (is_array($value))
                                                {{ json_encode($value) }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (! empty($item->addons))
                            <div class="mb-4">
                                <h4 class="mb-2 text-body-sm font-medium">{{ __('Addons') }}</h4>
                                <ul class="space-y-1 text-small text-muted-foreground">
                                    @foreach ($item->addons as $addonKey)
                                        <li>{{ $addonKey }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-end gap-3">
                            <form
                                method="POST"
                                action="{{ route('client.cart.items.update', $item) }}"
                                class="flex flex-wrap items-end gap-2"
                            >
                                @csrf
                                @method('PATCH')
                                <div class="w-28">
                                    <x-ui.input
                                        name="quantity"
                                        type="number"
                                        min="1"
                                        max="999"
                                        :label="__('Quantity')"
                                        :value="$item->quantity"
                                    />
                                </div>
                                <x-ui.button type="submit" variant="secondary" size="sm">
                                    {{ __('Update') }}
                                </x-ui.button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('client.cart.items.destroy', $item) }}"
                                onsubmit="return confirm(@js(__('Remove this item from your cart?')))"
                            >
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="danger" size="sm">
                                    {{ __('Remove') }}
                                </x-ui.button>
                            </form>
                        </div>
                    </x-ui.card>
                @endforeach

                <form method="POST" action="{{ route('client.cart.clear') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="ghost" size="sm">
                        {{ __('Clear cart') }}
                    </x-ui.button>
                </form>
            </div>

            <div class="space-y-6">
                <x-ui.card :title="__('Order summary')">
                    <dl class="space-y-3 text-body-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Items') }}</dt>
                            <dd>{{ $summary['item_count'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Recurring (excl. tax)') }}</dt>
                            <dd>{{ $summary['recurring_subtotal'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Setup fees') }}</dt>
                            <dd>{{ $summary['setup_subtotal'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('First payment (excl. tax)') }}</dt>
                            <dd>{{ $summary['first_payment_subtotal'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ $summary['tax_label'] }}</dt>
                            <dd>{{ $summary['first_payment_tax'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-border pt-3 font-medium">
                            <dt>{{ __('First payment total') }}</dt>
                            <dd>{{ $summary['first_payment_total'] }}</dd>
                        </div>
                    </dl>

                    @if ($summary['tax_is_estimate'])
                        <p class="mt-3 text-small text-muted-foreground">
                            {{ __('Tax is an estimate; final VAT is calculated at checkout.') }}
                        </p>
                    @endif

                    <div class="mt-6 flex flex-col gap-2">
                        <x-ui.button type="button" variant="primary" disabled>
                            {{ __('Proceed to checkout') }}
                        </x-ui.button>
                        <p class="text-center text-small text-muted-foreground">
                            {{ __('Checkout arrives in the next step.') }}
                        </p>
                        <x-ui.button :href="route('client.catalog.index')" variant="secondary">
                            {{ __('Continue shopping') }}
                        </x-ui.button>
                    </div>
                </x-ui.card>
            </div>
        </div>
    @endif
</x-layout.client>
