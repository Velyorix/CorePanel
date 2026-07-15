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
            {{ __('Container components are available via') }}
            <code class="text-small">&lt;x-ui.card&gt;</code>,
            <code class="text-small">&lt;x-ui.modal&gt;</code>,
            <code class="text-small">&lt;x-ui.dropdown&gt;</code>,
            <code class="text-small">&lt;x-ui.tabs&gt;</code>.
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

        <x-ui.card :title="__('Overview')" :padding="false">
            <div class="px-5 py-4">
                <x-ui.tabs
                    :items="[
                        ['name' => 'overview', 'label' => __('Overview')],
                        ['name' => 'activity', 'label' => __('Activity')],
                    ]"
                    active="overview"
                >
                    <x-slot:overview>
                        <p>{{ __('Your workspace is ready. Use the sidebar to navigate.') }}</p>
                    </x-slot:overview>
                    <x-slot:activity>
                        <p>{{ __('Recent activity will appear here.') }}</p>
                    </x-slot:activity>
                </x-ui.tabs>
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
