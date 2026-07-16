@php
    /** @var \Core\Clients\Models\Client|null $client */
    $client ??= null;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.select
        name="user_id"
        :label="__('Owner')"
        class="sm:col-span-2"
    >
        <option value="">{{ __('No owner') }}</option>
        @foreach ($users as $user)
            <option
                value="{{ $user->id }}"
                @selected((string) old('user_id', $client?->user_id) === (string) $user->id)
            >
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.input
        name="company_name"
        :label="__('Company name')"
        :value="old('company_name', $client?->company_name)"
        class="sm:col-span-2"
    />

    <x-ui.input
        name="vat_number"
        :label="__('VAT number')"
        :value="old('vat_number', $client?->vat_number)"
    />

    <x-ui.input
        name="phone"
        :label="__('Phone')"
        :value="old('phone', $client?->phone)"
        autocomplete="tel"
    />

    <x-ui.input
        name="address"
        :label="__('Address')"
        :value="old('address', $client?->address)"
        class="sm:col-span-2"
        autocomplete="street-address"
    />

    <x-ui.input
        name="city"
        :label="__('City')"
        :value="old('city', $client?->city)"
        autocomplete="address-level2"
    />

    <x-ui.input
        name="postal_code"
        :label="__('Postal code')"
        :value="old('postal_code', $client?->postal_code)"
        autocomplete="postal-code"
    />

    <x-ui.input
        name="country"
        :label="__('Country')"
        :value="old('country', $client?->country)"
        :hint="__('ISO 3166-1 alpha-2 code (e.g. BE, FR).')"
        maxlength="2"
        autocomplete="country"
    />

    <x-ui.select name="status" :label="__('Status')" required>
        @foreach ($statuses as $status)
            <option
                value="{{ $status->value }}"
                @selected(old('status', $client?->status?->value ?? \Core\Clients\Enums\ClientStatus::Active->value) === $status->value)
            >
                {{ __(ucfirst($status->value)) }}
            </option>
        @endforeach
    </x-ui.select>
</div>
