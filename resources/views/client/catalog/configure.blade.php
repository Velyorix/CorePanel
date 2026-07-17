@php
    $selectedCycle = old(
        'billing_cycle',
        $billingCycles[0]->value ?? \Core\Products\Enums\BillingCycle::Monthly->value,
    );
    $selectedPricing = $product->pricing->first(
        fn ($tier) => $tier->billing_cycle->value === $selectedCycle,
    );
    $initialPreview = $pricePreview ?? [
        'base_price' => $selectedPricing?->price ?? '0.00',
        'option_deltas' => '0.00',
        'addons_recurring' => '0.00',
        'unit_price' => $selectedPricing?->price ?? '0.00',
        'product_setup_fee' => $selectedPricing?->setup_fee ?? '0.00',
        'addons_setup_fee' => '0.00',
        'setup_fee' => $selectedPricing?->setup_fee ?? '0.00',
        'quantity' => 1,
        'recurring_subtotal' => $selectedPricing?->price ?? '0.00',
        'first_payment_subtotal' => $selectedPricing
            ? number_format((float) $selectedPricing->price + (float) $selectedPricing->setup_fee, 2, '.', '')
            : '0.00',
        'tax_rate' => '0.0000',
        'tax_label' => __('Tax (estimate)'),
        'tax_is_estimate' => true,
        'recurring_tax' => '0.00',
        'first_payment_tax' => '0.00',
        'recurring_total' => $selectedPricing?->price ?? '0.00',
        'first_payment_total' => $selectedPricing
            ? number_format((float) $selectedPricing->price + (float) $selectedPricing->setup_fee, 2, '.', '')
            : '0.00',
    ];
@endphp

<x-layout.client
    :title="__('Configure :product', ['product' => $product->name])"
    :page-heading="__('Configure :product', ['product' => $product->name])"
>
    <x-slot:subtitle>
        {{ __('Choose a billing cycle, options, and addons before adding to your cart.') }}
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
        @php
            $breadcrumbItems = [
                ['label' => __('Client'), 'url' => route('client.dashboard')],
                ['label' => __('Catalog'), 'url' => route('client.catalog.index')],
            ];

            if ($product->category) {
                $breadcrumbItems[] = [
                    'label' => $product->category->name,
                    'url' => route('client.catalog.category', $product->category->slug),
                ];
            }

            $breadcrumbItems[] = [
                'label' => $product->name,
                'url' => route('client.catalog.products.show', $product->slug),
            ];
            $breadcrumbItems[] = ['label' => __('Configure')];
        @endphp
        <x-ui.breadcrumb :items="$breadcrumbItems" />
    </x-slot:breadcrumbs>

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to add to cart')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('client.catalog.products.configure.store', $product->slug) }}"
        class="space-y-6"
        x-data="productConfiguratorPreview({
            previewUrl: @js(route('client.catalog.products.configure.preview', $product->slug)),
            initial: @js($initialPreview),
        })"
    >
        @csrf

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card :title="__('Billing cycle')">
                    <div class="space-y-3">
                        @foreach ($billingCycles as $cycle)
                            @php
                                $tier = $product->pricing->first(
                                    fn ($row) => $row->billing_cycle === $cycle,
                                );
                            @endphp
                            <label class="flex cursor-pointer items-start gap-3 rounded-md border border-border p-3 hover:bg-muted/40">
                                <input
                                    type="radio"
                                    name="billing_cycle"
                                    value="{{ $cycle->value }}"
                                    class="mt-1"
                                    @checked($selectedCycle === $cycle->value)
                                    required
                                >
                                <span class="flex-1">
                                    <span class="block font-medium">{{ $cycle->label() }}</span>
                                    @if ($tier)
                                        <span class="mt-1 block text-small text-muted-foreground">
                                            {{ $tier->price }}
                                            @if ((float) $tier->setup_fee > 0)
                                                · {{ __('Setup') }} {{ $tier->setup_fee }}
                                            @endif
                                        </span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @if (collect($billingCycles)->contains(\Core\Products\Enums\BillingCycle::Custom))
                        <div class="mt-4">
                            <x-ui.input
                                name="custom_interval_days"
                                type="number"
                                min="1"
                                :label="__('Custom interval (days)')"
                                :value="old('custom_interval_days', $product->pricingFor(\Core\Products\Enums\BillingCycle::Custom)?->custom_interval_days)"
                            />
                        </div>
                    @endif
                </x-ui.card>

                @if ($product->requiresHostnameInput())
                    @php
                        $hostnameKey = $product->hostnameOptionKey();
                        $hostnameOption = $hostnameKey ? $product->optionByKey($hostnameKey) : null;
                        $hostnameField = 'options['.$hostnameKey.']';
                        $hostnameOld = old('options.'.$hostnameKey);
                        $hostnameLabel = $hostnameOption?->name ?? $product->hostnameOptionLabel();
                    @endphp
                    <x-ui.card :title="$hostnameLabel">
                        <x-ui.input
                            :name="$hostnameField"
                            type="text"
                            :label="$hostnameLabel.' *'"
                            :value="$hostnameOld"
                            :maxlength="$hostnameOption?->config['max_length'] ?? 253"
                            :hint="$hostnameKey === 'domain'
                                ? __('Enter the domain name to register or manage, e.g. example.com')
                                : __('Enter a fully qualified hostname, e.g. node-01.example.com')"
                            required
                        />
                    </x-ui.card>
                @endif

                @if ($product->nonHostnameOptions()->isNotEmpty())
                    <x-ui.card :title="__('Options')">
                        <div class="space-y-4">
                            @foreach ($product->nonHostnameOptions() as $option)
                                @php
                                    $field = 'options['.$option->key.']';
                                    $oldValue = old('options.'.$option->key);
                                @endphp

                                @if ($option->type === \Core\Products\Enums\ProductOptionType::Select)
                                    <x-ui.select
                                        :name="$field"
                                        :label="$option->name.($option->required ? ' *' : '')"
                                    >
                                        <option value="">{{ __('Select…') }}</option>
                                        @foreach ($option->choices() as $choice)
                                            <option
                                                value="{{ $choice['value'] }}"
                                                @selected((string) $oldValue === (string) $choice['value'])
                                            >
                                                {{ $choice['label'] ?? $choice['value'] }}
                                                @if (isset($choice['price_delta']) && (float) $choice['price_delta'] != 0)
                                                    ({{ ((float) $choice['price_delta'] > 0 ? '+' : '').$choice['price_delta'] }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </x-ui.select>
                                @elseif ($option->type === \Core\Products\Enums\ProductOptionType::Checkbox)
                                    <label class="flex items-center gap-2 text-body-sm">
                                        <input type="hidden" name="{{ $field }}" value="0">
                                        <input
                                            type="checkbox"
                                            name="{{ $field }}"
                                            value="1"
                                            @checked(filter_var($oldValue ?? false, FILTER_VALIDATE_BOOLEAN))
                                        >
                                        <span>
                                            {{ $option->name }}
                                            @if ($option->required) * @endif
                                        </span>
                                    </label>
                                @elseif (in_array($option->type, [\Core\Products\Enums\ProductOptionType::Quantity, \Core\Products\Enums\ProductOptionType::Number], true))
                                    <x-ui.input
                                        :name="$field"
                                        type="number"
                                        :label="$option->name.($option->required ? ' *' : '')"
                                        :value="$oldValue"
                                        :min="$option->config['min'] ?? null"
                                        :max="$option->config['max'] ?? null"
                                        :step="$option->config['step'] ?? ($option->type === \Core\Products\Enums\ProductOptionType::Quantity ? '1' : 'any')"
                                    />
                                @else
                                    <x-ui.input
                                        :name="$field"
                                        type="text"
                                        :label="$option->name.($option->required ? ' *' : '')"
                                        :value="$oldValue"
                                        :maxlength="$option->config['max_length'] ?? null"
                                    />
                                @endif
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif

                @if ($product->addons->isNotEmpty())
                    <x-ui.card :title="__('Addons')">
                        <div class="space-y-3">
                            @foreach ($product->addons as $addon)
                                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-border p-3 hover:bg-muted/40">
                                    <input
                                        type="checkbox"
                                        name="addons[]"
                                        value="{{ $addon->key }}"
                                        class="mt-1"
                                        @checked(in_array($addon->key, old('addons', []), true))
                                    >
                                    <span class="flex-1">
                                        <span class="block font-medium">{{ $addon->name }}</span>
                                        <span class="mt-1 block text-small text-muted-foreground">
                                            {{ $addon->price }} / {{ $addon->billing_cycle->label() }}
                                            @if ((float) $addon->setup_fee > 0)
                                                · {{ __('Setup') }} {{ $addon->setup_fee }}
                                            @endif
                                        </span>
                                        @if ($addon->description)
                                            <span class="mt-1 block text-small text-muted-foreground">{{ $addon->description }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            </div>

            <div class="space-y-6">
                <x-ui.card :title="__('Summary')">
                    <dl class="space-y-3 text-body-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Product') }}</dt>
                            <dd class="text-end">{{ $product->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Base price') }}</dt>
                            <dd x-text="preview.base_price ?? '—'">{{ $initialPreview['base_price'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Options') }}</dt>
                            <dd x-text="preview.option_deltas ?? '0.00'">{{ $initialPreview['option_deltas'] ?? '0.00' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Addons') }}</dt>
                            <dd x-text="preview.addons_recurring ?? '0.00'">{{ $initialPreview['addons_recurring'] ?? '0.00' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 font-medium">
                            <dt>{{ __('Unit price') }}</dt>
                            <dd x-text="preview.unit_price ?? '—'">{{ $initialPreview['unit_price'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Setup fee') }}</dt>
                            <dd x-text="preview.setup_fee ?? '—'">{{ $initialPreview['setup_fee'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Recurring (excl. tax)') }}</dt>
                            <dd x-text="preview.recurring_subtotal ?? '—'">{{ $initialPreview['recurring_subtotal'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('First payment (excl. tax)') }}</dt>
                            <dd x-text="preview.first_payment_subtotal ?? '—'">{{ $initialPreview['first_payment_subtotal'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground" x-text="preview.tax_label || @js(__('Tax (estimate)'))"></dt>
                            <dd x-text="preview.first_payment_tax ?? '0.00'">{{ $initialPreview['first_payment_tax'] ?? '0.00' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-border pt-3 font-medium">
                            <dt>{{ __('First payment total') }}</dt>
                            <dd x-text="preview.first_payment_total ?? '—'">{{ $initialPreview['first_payment_total'] ?? '—' }}</dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-small text-muted-foreground" x-show="preview.tax_is_estimate" x-cloak>
                        {{ __('Tax is an estimate; final VAT is calculated at checkout.') }}
                    </p>

                    <p
                        class="mt-2 text-small text-danger-700 dark:text-danger-400"
                        x-show="error"
                        x-text="error"
                        x-cloak
                    ></p>

                    <p
                        class="mt-2 text-small text-muted-foreground"
                        x-show="loading"
                        x-cloak
                    >
                        {{ __('Updating price…') }}
                    </p>

                    <div class="mt-4">
                        <x-ui.input
                            name="quantity"
                            type="number"
                            min="1"
                            :label="__('Quantity')"
                            :value="old('quantity', 1)"
                        />
                    </div>

                    <div class="mt-6 flex flex-col gap-2">
                        <x-ui.button type="submit" variant="primary">
                            {{ __('Add to cart') }}
                        </x-ui.button>
                        <x-ui.button :href="route('client.catalog.products.show', $product->slug)" variant="secondary">
                            {{ __('Cancel') }}
                        </x-ui.button>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </form>
</x-layout.client>
