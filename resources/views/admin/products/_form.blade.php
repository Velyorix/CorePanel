@php
    /** @var \Core\Products\Models\Product|null $product */
    $product ??= null;
    $pricingByCycle = collect(old('pricing', []));

    if ($pricingByCycle->isEmpty() && $product !== null) {
        $pricingByCycle = $product->pricing->keyBy(fn ($tier) => $tier->billing_cycle->value);
    }
@endphp

<div class="space-y-8">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input
            name="name"
            :label="__('Name')"
            :value="old('name', $product?->name)"
            class="sm:col-span-2"
            required
        />

        <x-ui.input
            name="slug"
            :label="__('Slug')"
            :value="old('slug', $product?->slug)"
            :hint="__('Leave empty to generate from the name.')"
        />

        <x-ui.select name="type" :label="__('Type')" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected((string) old('type', $product?->type?->value ?? \Core\Products\Enums\ProductType::Other->value) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </x-ui.select>

        <x-ui.select name="category_id" :label="__('Category')">
            <option value="">{{ __('No category') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </x-ui.select>

        <x-ui.input
            name="module"
            :label="__('Provider module')"
            :value="old('module', $product?->module)"
            :hint="__('Optional lowercase module slug (e.g. pterodactyl).')"
        />

        <x-ui.input
            name="sort_order"
            type="number"
            :label="__('Sort order')"
            :value="old('sort_order', $product?->sort_order ?? 0)"
            min="0"
        />

        <div class="sm:col-span-2">
            <label for="description" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Description') }}</label>
            <textarea
                id="description"
                name="description"
                rows="4"
                class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >{{ old('description', $product?->description) }}</textarea>
            @error('description')
                <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <h2 class="text-body-sm font-medium text-foreground">{{ __('Pricing') }}</h2>
        <p class="mt-1 text-small text-muted-foreground">
            {{ __('Enable billing cycles and set recurring price plus setup fee.') }}
        </p>

        <div class="mt-4 overflow-x-auto rounded-lg border border-border">
            <table class="min-w-full divide-y divide-border text-body-sm">
                <thead class="bg-muted/40">
                    <tr>
                        <th class="px-3 py-2 text-start font-medium">{{ __('Cycle') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('Enabled') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('Price') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('Setup fee') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('Custom days') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($billingCycles as $cycle)
                        @php
                            $existing = $pricingByCycle->get($cycle->value);
                            $isEnabled = old("pricing.{$cycle->value}.enabled");
                            if ($isEnabled === null) {
                                $isEnabled = $existing
                                    ? (is_array($existing) ? ($existing['enabled'] ?? false) : $existing->is_enabled)
                                    : false;
                            } else {
                                $isEnabled = filter_var($isEnabled, FILTER_VALIDATE_BOOLEAN);
                            }

                            $price = old(
                                "pricing.{$cycle->value}.price",
                                is_array($existing) ? ($existing['price'] ?? '') : ($existing->price ?? ''),
                            );
                            $setupFee = old(
                                "pricing.{$cycle->value}.setup_fee",
                                is_array($existing) ? ($existing['setup_fee'] ?? '0') : ($existing->setup_fee ?? '0'),
                            );
                            $customDays = old(
                                "pricing.{$cycle->value}.custom_interval_days",
                                is_array($existing)
                                    ? ($existing['custom_interval_days'] ?? '')
                                    : ($existing->custom_interval_days ?? ''),
                            );
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium">
                                {{ $cycle->label() }}
                                @if ($cycle->isOptional())
                                    <span class="text-small text-muted-foreground">({{ __('optional') }})</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <input type="hidden" name="pricing[{{ $cycle->value }}][enabled]" value="0">
                                <input
                                    type="checkbox"
                                    name="pricing[{{ $cycle->value }}][enabled]"
                                    value="1"
                                    @checked($isEnabled)
                                    class="rounded border-border"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="pricing[{{ $cycle->value }}][price]"
                                    value="{{ $price }}"
                                    class="w-28 rounded-md border border-border bg-surface px-2 py-1.5 text-body-sm"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="pricing[{{ $cycle->value }}][setup_fee]"
                                    value="{{ $setupFee }}"
                                    class="w-28 rounded-md border border-border bg-surface px-2 py-1.5 text-body-sm"
                                >
                            </td>
                            <td class="px-3 py-2">
                                @if ($cycle === \Core\Products\Enums\BillingCycle::Custom)
                                    <input
                                        type="number"
                                        min="1"
                                        name="pricing[{{ $cycle->value }}][custom_interval_days]"
                                        value="{{ $customDays }}"
                                        class="w-24 rounded-md border border-border bg-surface px-2 py-1.5 text-body-sm"
                                    >
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
