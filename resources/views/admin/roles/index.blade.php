<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Roles — {{ config('corepanel.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-screen max-w-5xl flex-col px-4 py-12">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Roles</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Manage roles, inheritance, and assigned permissions.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.permissions.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Permissions
                    </a>
                    @can('create', Core\Permissions\Models\Role::class)
                        <a href="{{ route('admin.roles.create') }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                            Create role
                        </a>
                    @endcan
                </div>
            </div>

            @if (session('status'))
                <p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                    {{ session('status') }}
                </p>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 text-left text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Parent</th>
                            <th class="px-4 py-3 font-medium">Permissions</th>
                            <th class="px-4 py-3 font-medium">Users</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($roles as $role)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $role->name }}</div>
                                    @if ($role->is_system)
                                        <div class="text-xs text-zinc-500">System</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-500">{{ $role->parent?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-zinc-500">{{ $role->permissions->count() }}</td>
                                <td class="px-4 py-3 text-zinc-500">{{ $role->users_count }}</td>
                                <td class="px-4 py-3 text-end">
                                    <a href="{{ route('admin.roles.show', $role) }}" class="font-medium text-zinc-700 hover:underline dark:text-zinc-300">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No roles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </main>
    </body>
</html>
