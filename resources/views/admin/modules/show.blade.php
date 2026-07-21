@php
    /** @var array{
     *     key: string,
     *     name: string,
     *     version: string,
     *     description: string|null,
     *     capabilities: list<string>,
     *     path: string,
     *     installed: bool,
     *     enabled: bool,
     *     loaded: bool,
     *     installation: \Core\Modules\Models\InstalledModule|null,
     *     providers: list<string>,
     *     authors: list<string>,
     *     requires: array{corepanel?: string, php?: string},
     *     config_json: string
     * } $module
     */
@endphp

<x-layout.admin
    :title="$module['name']"
    :page-heading="$module['name']"
>
    <x-slot:subtitle>
        {{ __('Module details, lifecycle actions, and configuration.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Modules'), 'url' => route('admin.modules.index')],
            ['label' => $module['name']],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('admin.modules.index')" variant="secondary" size="sm">
            {{ __('Back to list') }}
        </x-ui.button>

        @if ($canManage)
            <div class="flex flex-wrap items-center gap-2">
                @unless ($module['installed'])
                    <form method="POST" action="{{ route('admin.modules.install', $module['key']) }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Install') }}
                        </x-ui.button>
                    </form>
                @endunless

                @if ($module['installed'] && ! $module['enabled'])
                    <form method="POST" action="{{ route('admin.modules.enable', $module['key']) }}">
                        @csrf
                        <x-ui.button type="submit" variant="primary" size="sm">
                            {{ __('Enable') }}
                        </x-ui.button>
                    </form>
                @endif

                @if ($module['enabled'])
                    <form method="POST" action="{{ route('admin.modules.disable', $module['key']) }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Disable') }}
                        </x-ui.button>
                    </form>
                @endif

                @if ($module['installed'])
                    <form
                        method="POST"
                        action="{{ route('admin.modules.uninstall', $module['key']) }}"
                        onsubmit="return confirm(@js(__('Uninstall this module from the registry?')))"
                    >
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="danger" size="sm">
                            {{ __('Uninstall') }}
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
                    <dd class="font-mono text-small">{{ $module['key'] }}</dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Version') }}</dt>
                    <dd class="font-mono text-small">{{ $module['version'] }}</dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd class="flex flex-wrap justify-end gap-1.5">
                        @if ($module['enabled'])
                            <x-ui.badge variant="success">{{ __('Enabled') }}</x-ui.badge>
                        @elseif ($module['installed'])
                            <x-ui.badge variant="warning">{{ __('Installed') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral">{{ __('Discovered') }}</x-ui.badge>
                        @endif

                        @if ($module['loaded'])
                            <x-ui.badge variant="primary">{{ __('Loaded') }}</x-ui.badge>
                        @endif
                    </dd>
                </div>

                @if (filled($module['description']))
                    <div>
                        <dt class="mb-1 text-muted-foreground">{{ __('Description') }}</dt>
                        <dd>{{ $module['description'] }}</dd>
                    </div>
                @endif

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Capabilities') }}</dt>
                    <dd class="text-end">
                        {{ $module['capabilities'] === [] ? '—' : implode(', ', $module['capabilities']) }}
                    </dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Authors') }}</dt>
                    <dd class="text-end">
                        {{ $module['authors'] === [] ? '—' : implode(', ', $module['authors']) }}
                    </dd>
                </div>

                <div>
                    <dt class="mb-1 text-muted-foreground">{{ __('Path') }}</dt>
                    <dd class="break-all font-mono text-small text-muted-foreground">{{ $module['path'] }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Installation')">
            @if ($module['installation'] === null)
                <p class="text-body-sm text-muted-foreground">
                    {{ __('This module is present on disk but not registered in installed_modules.') }}
                </p>
            @else
                <dl class="space-y-3 text-body-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Installed at') }}</dt>
                        <dd>{{ $module['installation']->installed_at?->toDayDateTimeString() ?: '—' }}</dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Enabled at') }}</dt>
                        <dd>{{ $module['installation']->enabled_at?->toDayDateTimeString() ?: '—' }}</dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Checksum') }}</dt>
                        <dd class="max-w-xs break-all font-mono text-small">
                            {{ $module['installation']->checksum ?: '—' }}
                        </dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Signature') }}</dt>
                        <dd class="max-w-xs break-all font-mono text-small">
                            {{ $module['installation']->signature ?: '—' }}
                        </dd>
                    </div>

                    @if ($module['requires'] !== [])
                        <div>
                            <dt class="mb-1 text-muted-foreground">{{ __('Requirements') }}</dt>
                            <dd class="font-mono text-small">
                                @foreach ($module['requires'] as $requirement => $constraint)
                                    <div>{{ $requirement }}: {{ $constraint }}</div>
                                @endforeach
                            </dd>
                        </div>
                    @endif

                    @if ($module['providers'] !== [])
                        <div>
                            <dt class="mb-1 text-muted-foreground">{{ __('Providers') }}</dt>
                            <dd class="space-y-1 font-mono text-small">
                                @foreach ($module['providers'] as $provider)
                                    <div>{{ $provider }}</div>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif
        </x-ui.card>
    </div>

    @if ($module['installed'] && $canManage)
        <div class="mt-6">
            <x-ui.card :title="__('Configuration')">
                <form method="POST" action="{{ route('admin.modules.config', $module['key']) }}" class="space-y-4">
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
                        >{{ old('config', $module['config_json']) }}</textarea>
                        @error('config')
                            <p class="mt-1.5 text-small text-danger">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-small text-muted-foreground">
                            {{ __('Leave empty to clear stored configuration.') }}
                        </p>
                    </div>

                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Save configuration') }}
                    </x-ui.button>
                </form>
            </x-ui.card>
        </div>
    @endif
</x-layout.admin>
