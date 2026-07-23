<x-layout.admin
    :title="__('Billing')"
    :page-heading="__('Billing')"
>
    <x-slot:subtitle>
        {{ __('Document prefixes, currency, tax preview, and renewals.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('Billing')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <x-ui.card :title="__('Payment gateways')">
            <p class="mb-4 text-body-sm text-muted-foreground">
                {{ __('Enable, order, and configure payment providers separately.') }}
            </p>
            <x-ui.button :href="route('admin.gateways.index')" variant="secondary" size="sm">
                {{ __('Manage payment gateways') }}
            </x-ui.button>
        </x-ui.card>

        <x-ui.card :title="__('Billing settings')">
            @if ($canManage)
                <form method="POST" action="{{ route('admin.settings.billing.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 md:grid-cols-3">
                        <x-ui.input
                            name="invoice_prefix"
                            :label="__('Invoice prefix')"
                            :value="old('invoice_prefix', $values['invoice_prefix'])"
                            required
                        />
                        <x-ui.input
                            name="quote_prefix"
                            :label="__('Quote prefix')"
                            :value="old('quote_prefix', $values['quote_prefix'])"
                            required
                        />
                        <x-ui.input
                            name="credit_note_prefix"
                            :label="__('Credit note prefix')"
                            :value="old('credit_note_prefix', $values['credit_note_prefix'])"
                            required
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input
                            name="default_currency"
                            :label="__('Default currency')"
                            :value="old('default_currency', $values['default_currency'])"
                            maxlength="3"
                            :hint="__('ISO 4217 code, e.g. EUR')"
                            required
                        />
                        <x-ui.input
                            name="tax_preview_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            :label="__('Tax preview rate (%)')"
                            :value="old('tax_preview_rate', $values['tax_preview_rate'])"
                            required
                        />
                    </div>

                    <label class="flex items-center gap-2 text-body-sm text-foreground">
                        <input
                            type="checkbox"
                            name="renewal_enabled"
                            value="1"
                            class="rounded border-border"
                            @checked(old('renewal_enabled', $values['renewal_enabled']))
                        >
                        {{ __('Enable automatic renewals') }}
                    </label>

                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Save settings') }}
                    </x-ui.button>
                </form>
            @else
                <dl class="space-y-3 text-body-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Invoice prefix') }}</dt>
                        <dd class="font-medium">{{ $values['invoice_prefix'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Quote prefix') }}</dt>
                        <dd class="font-medium">{{ $values['quote_prefix'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Credit note prefix') }}</dt>
                        <dd class="font-medium">{{ $values['credit_note_prefix'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Default currency') }}</dt>
                        <dd class="font-medium">{{ $values['default_currency'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Tax preview rate (%)') }}</dt>
                        <dd class="font-medium">{{ $values['tax_preview_rate'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Automatic renewals') }}</dt>
                        <dd class="font-medium">{{ $values['renewal_enabled'] ? __('Enabled') : __('Disabled') }}</dd>
                    </div>
                </dl>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>
