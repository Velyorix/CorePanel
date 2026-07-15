<x-layout.app :title="__('Dashboard')">
    <x-slot:sidebar>
        <nav class="space-y-1" aria-label="{{ __('Sidebar') }}">
            <a
                href="{{ route('dashboard') }}"
                class="block rounded-md bg-muted px-3 py-2 text-body-sm font-medium text-foreground"
                aria-current="page"
            >
                {{ __('Dashboard') }}
            </a>
            <a
                href="{{ route('account.sessions.index') }}"
                class="block rounded-md px-3 py-2 text-body-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
            >
                {{ __('Sessions') }}
            </a>
            @permission('roles.view')
                <a
                    href="{{ route('admin.roles.index') }}"
                    class="block rounded-md px-3 py-2 text-body-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                >
                    {{ __('Roles') }}
                </a>
            @endpermission
        </nav>
    </x-slot:sidebar>

    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            @auth
                <span class="hidden text-body-sm text-muted-foreground sm:inline">
                    {{ auth()->user()->email }}
                </span>
                <x-ui.badge variant="neutral">{{ __('Signed in') }}</x-ui.badge>
                <x-ui.dropdown align="right">
                    <x-slot:trigger>
                        <x-ui.button type="button" variant="secondary" size="sm">
                            {{ __('Account') }}
                        </x-ui.button>
                    </x-slot:trigger>
                    <x-ui.dropdown-item :href="route('account.sessions.index')">
                        {{ __('Sessions') }}
                    </x-ui.dropdown-item>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.dropdown-item type="submit">
                            {{ __('Log out') }}
                        </x-ui.dropdown-item>
                    </form>
                </x-ui.dropdown>
            @endauth
        </div>
    </x-slot:topbar>

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1>{{ __('Dashboard') }}</h1>
            <p class="mt-2 text-muted-foreground">
                {{ __('Welcome back. This page uses the CorePanel app layout shell.') }}
            </p>
        </div>

        <x-ui.alert variant="info" title="{{ __('Design system') }}">
            {{ __('Data tables are available via') }}
            <code class="text-small">&lt;x-ui.table&gt;</code>
            {{ __('with filter, sort, and pagination slots.') }}
        </x-ui.alert>

        <x-ui.card :title="__('Quick links')">
            <div class="flex flex-wrap gap-2">
                <x-ui.button :href="route('account.sessions.index')" variant="secondary" size="sm">
                    {{ __('Manage active sessions') }}
                </x-ui.button>
                @permission('roles.view')
                    <x-ui.button :href="route('admin.roles.index')" variant="secondary" size="sm">
                        {{ __('Manage roles') }}
                    </x-ui.button>
                @endpermission
                <x-ui.button
                    type="button"
                    variant="primary"
                    size="sm"
                    x-on:click="$dispatch('open-modal', 'dashboard-demo')"
                >
                    {{ __('Open demo modal') }}
                </x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Sample table')" :padding="false">
            <div class="p-5">
                <x-ui.table :paginator="$demoRows">
                    <x-slot:filters>
                        <form method="GET" action="{{ route('dashboard') }}" class="flex w-full flex-wrap items-end gap-3">
                            <div class="min-w-56 flex-1">
                                <x-ui.input
                                    name="q"
                                    label="{{ __('Search') }}"
                                    :value="request('q')"
                                    placeholder="{{ __('Filter demo rows…') }}"
                                />
                            </div>
                            <x-ui.button type="submit" variant="secondary" size="sm">
                                {{ __('Apply') }}
                            </x-ui.button>
                        </form>
                    </x-slot:filters>

                    <x-slot:actions>
                        <x-ui.badge variant="primary">{{ __('Bulk actions slot') }}</x-ui.badge>
                    </x-slot:actions>

                    <x-slot:head>
                        <tr>
                            <x-ui.table-heading sort="name">{{ __('Name') }}</x-ui.table-heading>
                            <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                        </tr>
                    </x-slot:head>

                    @foreach ($demoRows as $row)
                        <tr class="hover:bg-muted/40">
                            <td class="px-4 py-3 font-medium">{{ $row['name'] }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :variant="$row['status'] === 'active' ? 'success' : 'warning'">
                                    {{ __(ucfirst($row['status'])) }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:empty>
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-muted-foreground">
                                {{ __('No demo rows found.') }}
                            </td>
                        </tr>
                    </x-slot:empty>
                </x-ui.table>
            </div>
        </x-ui.card>
    </div>

    <x-ui.modal name="dashboard-demo" :title="__('Demo modal')" size="sm">
        <p>{{ __('Modals are driven by Alpine.js open-modal / close-modal window events.') }}</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'dashboard-demo')">
                {{ __('Close') }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</x-layout.app>
