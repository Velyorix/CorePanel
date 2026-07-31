<x-layout.client
    :title="__('Checkout')"
    :page-heading="__('Checkout')"
>
    <x-slot:subtitle>
        {{ __('Confirm your billing details and payment method.') }}
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
            ['label' => __('Checkout')],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue checkout')">
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
            <form
                method="POST"
                action="{{ route('client.checkout.store') }}"
                class="space-y-6"
                id="checkout-form"
            >
                @csrf

                <x-ui.card :title="__('Billing details')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-ui.input
                                name="contact_name"
                                :label="__('Contact name')"
                                :value="old('contact_name', $draft->contactName)"
                                required
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <x-ui.input
                                name="contact_email"
                                type="email"
                                :label="__('Contact email')"
                                :value="old('contact_email', $draft->contactEmail)"
                                required
                            />
                        </div>
                        <x-ui.input
                            name="company_name"
                            :label="__('Company')"
                            :value="old('company_name', $draft->companyName)"
                        />
                        <x-ui.input
                            name="vat_number"
                            :label="__('VAT number')"
                            :value="old('vat_number', $draft->vatNumber)"
                        />
                        <div class="sm:col-span-2">
                            <x-ui.input
                                name="address"
                                :label="__('Address')"
                                :value="old('address', $draft->address)"
                                required
                            />
                        </div>
                        <x-ui.input
                            name="city"
                            :label="__('City')"
                            :value="old('city', $draft->city)"
                            required
                        />
                        <x-ui.input
                            name="postal_code"
                            :label="__('Postal code')"
                            :value="old('postal_code', $draft->postalCode)"
                            required
                        />
                        <x-ui.input
                            name="country"
                            :label="__('Country (ISO)')"
                            :value="old('country', $draft->country)"
                            maxlength="2"
                            required
                            :hint="__('Two-letter country code, e.g. FR or BE.')"
                        />
                        <x-ui.input
                            name="phone"
                            :label="__('Phone')"
                            :value="old('phone', $draft->phone)"
                        />
                    </div>
                </x-ui.card>

                @if ($couponEnabled)
                    <x-ui.card :title="__('Coupon')">
                        <x-ui.input
                            name="coupon_code"
                            :label="__('Coupon code')"
                            :value="old('coupon_code', $draft->couponCode)"
                            :hint="__('Discounts are applied when the order is placed.')"
                        />
                        <p class="mt-3 text-small text-muted-foreground">
                            {{ __('You can also apply the coupon now to save it before continuing.') }}
                        </p>
                    </x-ui.card>
                @endif

                <x-ui.card :title="__('Payment method')">
                    @if ($paymentMethods === [])
                        <x-ui.alert variant="warning">
                            {{ __('No payment methods are available right now. Please contact support.') }}
                        </x-ui.alert>
                    @else
                        <div class="space-y-3">
                            @foreach ($paymentMethods as $method)
                                @php
                                    $isEnabled = $method['enabled'];
                                    $selected = old('payment_method', $draft->paymentMethod) === $method['key'];
                                @endphp
                                <label @class([
                                    'flex cursor-pointer items-start gap-3 rounded-md border border-border p-3',
                                    'hover:bg-muted/40' => $isEnabled,
                                    'cursor-not-allowed opacity-60' => ! $isEnabled,
                                ])>
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="{{ $method['key'] }}"
                                        class="mt-1"
                                        @checked($selected && $isEnabled)
                                        @disabled(! $isEnabled)
                                        @if ($isEnabled) required @endif
                                    >
                                    <span class="flex-1">
                                        <span class="block font-medium">{{ $method['label'] }}</span>
                                        @if ($method['hint'])
                                            <span class="mt-1 block text-small text-muted-foreground">
                                                {{ $method['hint'] }}
                                            </span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit" variant="primary" :disabled="$paymentMethods === []">
                        {{ __('Save and continue') }}
                    </x-ui.button>
                    <x-ui.button :href="route('client.cart.index')" variant="secondary">
                        {{ __('Back to cart') }}
                    </x-ui.button>
                </div>
            </form>

            @if ($couponEnabled)
                <x-ui.card :title="__('Apply coupon early')">
                    <form
                        method="POST"
                        action="{{ route('client.checkout.coupon.apply') }}"
                        class="flex flex-wrap items-end gap-2"
                    >
                        @csrf
                        @method('PATCH')
                        <div class="min-w-[12rem] flex-1">
                            <x-ui.input
                                name="coupon_code"
                                :label="__('Coupon code')"
                                :value="old('coupon_code', $draft->couponCode)"
                            />
                        </div>
                        <x-ui.button type="submit" variant="secondary">
                            {{ __('Apply coupon') }}
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            @include('client.partials.order-summary', ['summary' => $summary])
        </div>
    </div>
</x-layout.client>
