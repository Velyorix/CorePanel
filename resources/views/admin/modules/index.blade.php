<x-layout.admin
    :title="__('Modules')"
    :page-heading="__('Modules')"
>
    <x-slot:subtitle>
        {{ __('Discover, install, and manage CorePanel modules.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Modules')],
        ]" />
    </x-slot:breadcrumbs>

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

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Module') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Version') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Capabilities') }}</th>
                <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
            </tr>
        </x-slot:head>

        @forelse ($modules as $module)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.modules.show', $module['key']) }}" class="font-medium text-primary hover:underline">
                        {{ $module['name'] }}
                    </a>
                    <div class="mt-0.5 font-mono text-small text-muted-foreground">{{ $module['key'] }}</div>
                    @if (filled($module['description']))
                        <div class="mt-1 max-w-md text-small text-muted-foreground">{{ $module['description'] }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 font-mono text-small">{{ $module['version'] }}</td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1.5">
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
                    </div>
                </td>
                <td class="px-4 py-3 text-small text-muted-foreground">
                    {{ $module['capabilities'] === [] ? '—' : implode(', ', $module['capabilities']) }}
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-ui.button :href="route('admin.modules.show', $module['key'])" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>

                        @if ($canManage)
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
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                    {{ __('No modules discovered in the modules directory.') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-layout.admin>
