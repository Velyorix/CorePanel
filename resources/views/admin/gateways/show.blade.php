@php
    /** @var array{key: string, label: string, enabled: bool, sort_order: int, config_json: string, registered: bool} $gateway */
@endphp

<x-layout.admin
    :title="$gateway['label']"
    :page-heading="$gateway['label']"
>
    <x-slot:subtitle>
        {{ __('Gateway status, display order, and encrypted configuration.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('Payment gateways'), 'url' => route('admin.gateways.index')],
            ['label' => $gateway['label']],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('admin.gateways.index')" variant="secondary" size="sm">
            {{ __('Back to list') }}
        </x-ui.button>

        @if ($canManage)
            <div class="flex flex-wrap items-center gap-2">
                @if ($gateway['enabled'])
                    <form method="POST" action="{{ route('admin.gateways.disable', $gateway['key']) }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Disable') }}
                        </x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.gateways.enable', $gateway['key']) }}">
                        @csrf
                        <x-ui.button type="submit" variant="primary" size="sm">
                            {{ __('Enable') }}
                        </x-ui.button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Key') }}</dt>
                    <dd class="font-mono text-small">{{ $gateway['key'] }}</dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Label') }}</dt>
                    <dd class="font-medium">{{ $gateway['label'] }}</dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        @if ($gateway['enabled'])
                            <x-ui.badge variant="success">{{ __('Enabled') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral">{{ __('Disabled') }}</x-ui.badge>
                        @endif
                    </dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Display order') }}</dt>
                    <dd class="font-mono text-small">{{ $gateway['sort_order'] }}</dd>
                </div>
            </dl>
        </x-ui.card>

        @if ($canManage)
            <x-ui.card :title="__('Configuration')">
                <form method="POST" action="{{ route('admin.gateways.config', $gateway['key']) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="config" class="mb-1.5 block text-body-sm font-medium text-foreground">
                            {{ __('JSON config') }}
                        </label>
                        <textarea
                            id="config"
                            name="config"
                            rows="12"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 font-mono text-small text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            spellcheck="false"
                        >{{ old('config', $gateway['config_json']) }}</textarea>
                        @error('config')
                            <p class="mt-1.5 text-small text-danger">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-small text-muted-foreground">
                            {{ __('Credentials are stored encrypted. Leave empty to clear stored configuration.') }}
                        </p>
                    </div>

                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Save configuration') }}
                    </x-ui.button>
                </form>
            </x-ui.card>
        @else
            <x-ui.card :title="__('Configuration')">
                <p class="text-body-sm text-muted-foreground">
                    {{ __('You do not have permission to edit gateway configuration.') }}
                </p>
            </x-ui.card>
        @endif
    </div>
</x-layout.admin>
