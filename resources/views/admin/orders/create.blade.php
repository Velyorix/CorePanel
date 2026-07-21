@php
    use Core\Products\Enums\BillingCycle;

    $initialClientId = old('client_id', $selectedClient?->id);
@endphp

<x-layout.admin
    :title="__('Create order')"
    :page-heading="__('Create order')"
>
    <x-slot:subtitle>
        {{ __('Place an order on behalf of a client.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Orders'), 'url' => route('admin.orders.index')],
            ['label' => __('Create')],
        ]" />
    </x-slot:breadcrumbs>

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to create order')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-6">
        @csrf

        <x-ui.card :title="__('Client')">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.select name="client_id" :label="__('Client')" required>
                    <option value="">{{ __('Select a client') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) $initialClientId === (string) $client->id)>
                            {{ $client->company_name ?: __('Client #'.$client->id) }}
                            @if ($client->owner)
                                — {{ $client->owner->email }}
                            @endif
                        </option>
                    @endforeach
                </x-ui.select>

                <div class="flex items-end">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onclick="const id = this.form.client_id.value; if (id) window.location = @js(route('admin.orders.create')) + '?client_id=' + id;"
                    >
                        {{ __('Prefill billing from client') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Billing details')">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input name="contact_name" :label="__('Contact name')" :value="old('contact_name', $draft->contactName)" required />
                <x-ui.input name="contact_email" type="email" :label="__('Contact email')" :value="old('contact_email', $draft->contactEmail)" required />
                <x-ui.input name="company_name" :label="__('Company')" :value="old('company_name', $draft->companyName)" />
                <x-ui.input name="vat_number" :label="__('VAT number')" :value="old('vat_number', $draft->vatNumber)" />
                <x-ui.input name="address" :label="__('Address')" :value="old('address', $draft->address)" required />
                <x-ui.input name="city" :label="__('City')" :value="old('city', $draft->city)" required />
                <x-ui.input name="postal_code" :label="__('Postal code')" :value="old('postal_code', $draft->postalCode)" required />
                <x-ui.input name="country" :label="__('Country')" :value="old('country', $draft->country)" maxlength="2" required />
                <x-ui.input name="phone" :label="__('Phone')" :value="old('phone', $draft->phone)" />
                <x-ui.select name="payment_method" :label="__('Payment method')" :required="$paymentMethods !== []">
                    <option value="">{{ __('Select payment method') }}</option>
                    @forelse ($paymentMethods as $method)
                        @if ($method['enabled'])
                            <option value="{{ $method['key'] }}" @selected(old('payment_method', $draft->paymentMethod) === $method['key'])>
                                {{ $method['label'] }}
                            </option>
                        @endif
                    @empty
                        <option value="" disabled>{{ __('No payment methods available') }}</option>
                    @endforelse
                </x-ui.select>
                <x-ui.input name="coupon_code" :label="__('Coupon')" :value="old('coupon_code', $draft->couponCode)" />
                <div class="md:col-span-2">
                    <label for="notes" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Internal notes') }}</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Line item')">
            @if ($products === [])
                <x-ui.alert variant="warning">
                    {{ __('Publish at least one product before creating an order.') }}
                </x-ui.alert>
            @else
                <div class="grid gap-4 md:grid-cols-3">
                    <x-ui.select name="items[0][product_id]" :label="__('Product')" required>
                        <option value="">{{ __('Select a product') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product['id'] }}" @selected((string) old('items.0.product_id') === (string) $product['id'])>
                                {{ $product['name'] }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="items[0][billing_cycle]" :label="__('Billing cycle')" required>
                        <option value="">{{ __('Select a cycle') }}</option>
                        @foreach (BillingCycle::cases() as $cycle)
                            <option value="{{ $cycle->value }}" @selected(old('items.0.billing_cycle') === $cycle->value)>
                                {{ $cycle->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input
                        name="items[0][quantity]"
                        type="number"
                        min="1"
                        max="100"
                        :label="__('Quantity')"
                        :value="old('items.0.quantity', 1)"
                        required
                    />
                </div>
                <p class="mt-3 text-small text-muted-foreground">
                    {{ __('Use a product without required options for admin-created orders. Complex configurations can use the client configurator via impersonation.') }}
                </p>
            @endif
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-body-sm">
                <input
                    type="checkbox"
                    name="submit_as_pending"
                    value="1"
                    class="rounded border-border"
                    @checked(old('submit_as_pending'))
                >
                <span>{{ __('Mark as pending payment immediately') }}</span>
            </label>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button :href="route('admin.orders.index')" variant="secondary">
                    {{ __('Cancel') }}
                </x-ui.button>
                @if ($products !== [])
                    <x-ui.button type="submit" variant="primary">
                        {{ __('Create order') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </form>
</x-layout.admin>
