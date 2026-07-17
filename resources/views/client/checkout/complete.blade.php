<x-layout.client
    :title="__('Checkout saved')"
    :page-heading="__('Review and place order')"
>
    <x-slot:subtitle>
        {{ __('Confirm your details, then place the order to proceed to payment.') }}
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
            ['label' => __('Cart'), 'url' => route('client.cart.index')],
            ['label' => __('Checkout'), 'url' => route('client.checkout.index')],
            ['label' => __('Review')],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to place order')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('Saved details')">
                <dl class="space-y-3 text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Contact') }}</dt>
                        <dd class="text-end">{{ $draft->contactName }} · {{ $draft->contactEmail }}</dd>
                    </div>
                    @if ($draft->companyName)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Company') }}</dt>
                            <dd>{{ $draft->companyName }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Address') }}</dt>
                        <dd class="text-end">
                            {{ $draft->address }}, {{ $draft->postalCode }} {{ $draft->city }}, {{ $draft->country }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Payment method') }}</dt>
                        <dd>{{ $draft->paymentMethod }}</dd>
                    </div>
                    @if ($draft->couponCode)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Coupon') }}</dt>
                            <dd>{{ $draft->couponCode }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-6 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('client.checkout.place') }}">
                        @csrf
                        <x-ui.button type="submit" variant="primary">
                            {{ __('Place order') }}
                        </x-ui.button>
                    </form>
                    <x-ui.button :href="route('client.checkout.index')" variant="secondary">
                        {{ __('Edit checkout') }}
                    </x-ui.button>
                    <x-ui.button :href="route('client.cart.index')" variant="ghost">
                        {{ __('Back to cart') }}
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @include('client.partials.order-summary', ['summary' => $summary])
        </div>
    </div>
</x-layout.client>
