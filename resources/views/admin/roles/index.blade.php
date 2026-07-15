<x-layout.admin
    :title="__('Roles')"
    :page-heading="__('Roles')"
>
    <x-slot:subtitle>
        {{ __('Manage roles, inheritance, and assigned permissions.') }}
    </x-slot:subtitle>

    <x-slot:sidebar>
        <nav class="space-y-1" aria-label="{{ __('Admin navigation') }}">
            <a
                href="{{ route('admin.roles.index') }}"
                class="block rounded-md bg-muted px-3 py-2 text-body-sm font-medium text-foreground"
                aria-current="page"
            >
                {{ __('Roles') }}
            </a>
            <a
                href="{{ route('admin.permissions.index') }}"
                class="block rounded-md px-3 py-2 text-body-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
            >
                {{ __('Permissions') }}
            </a>
        </nav>
    </x-slot:sidebar>

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
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.roles.index')],
            ['label' => __('Roles')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.permissions.index')" variant="secondary" size="sm">
            {{ __('Permissions') }}
        </x-ui.button>
        @can('create', Core\Permissions\Models\Role::class)
            <x-ui.button :href="route('admin.roles.create')" variant="primary" size="sm">
                {{ __('Create role') }}
            </x-ui.button>
        @endcan
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

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Parent') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Permissions') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Users') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        @forelse ($roles as $role)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $role->name }}</div>
                    @if ($role->is_system)
                        <div class="text-small text-muted-foreground">{{ __('System') }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $role->parent?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $role->permissions->count() }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $role->users_count }}</td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.roles.show', $role)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="p-4">
                    <x-ui.empty
                        :title="__('No roles found')"
                        :description="__('Create a role to get started.')"
                    />
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-layout.admin>
