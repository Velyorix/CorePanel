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
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Log out') }}
                    </x-ui.button>
                </form>
            @endauth
        </div>
    </x-slot:topbar>

    <div class="mx-auto max-w-5xl">
        <h1>{{ __('Dashboard') }}</h1>
        <p class="mt-2 text-muted-foreground">
            {{ __('Welcome back. This page uses the CorePanel app layout shell.') }}
        </p>

        <div class="mt-6">
            <x-ui.alert variant="info" title="{{ __('Design system') }}">
                {{ __('Form and feedback components are available via') }}
                <code class="text-small">&lt;x-ui.*&gt;</code>.
            </x-ui.alert>
        </div>

        <div class="mt-8 rounded-lg border border-border bg-surface p-5">
            <h2 class="text-h3">{{ __('Quick links') }}</h2>
            <ul class="mt-3 space-y-2 text-body-sm">
                <li>
                    <x-ui.button :href="route('account.sessions.index')" variant="ghost" size="sm">
                        {{ __('Manage active sessions') }}
                    </x-ui.button>
                </li>
                @permission('roles.view')
                    <li>
                        <x-ui.button :href="route('admin.roles.index')" variant="ghost" size="sm">
                            {{ __('Manage roles') }}
                        </x-ui.button>
                    </li>
                @endpermission
            </ul>
        </div>
    </div>
</x-layout.app>
