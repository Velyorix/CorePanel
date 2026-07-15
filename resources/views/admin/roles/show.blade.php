<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $role->name }} — {{ config('corepanel.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-screen max-w-4xl flex-col px-4 py-12">
            <div class="mb-8">
                <a href="{{ route('admin.roles.index') }}" class="text-sm font-medium text-zinc-600 hover:underline dark:text-zinc-400">&larr; Back to roles</a>
                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ $role->name }}</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $role->description ?: 'No description' }}
                            @if ($role->is_system)
                                · System role
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can('update', $role)
                            <a href="{{ route('admin.roles.edit', $role) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Edit
                            </a>
                        @endcan
                        @can('duplicate', $role)
                            <form method="POST" action="{{ route('admin.roles.duplicate', $role) }}" class="flex gap-2">
                                @csrf
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name', $role->name.'-copy') }}"
                                    class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                    required
                                >
                                <button type="submit" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Duplicate
                                </button>
                            </form>
                        @endcan
                        @can('delete', $role)
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950">
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </div>
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

            <div class="space-y-6">
                <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="font-medium">Hierarchy</h2>
                    <dl class="mt-3 space-y-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <div>
                            <dt class="inline font-medium text-zinc-700 dark:text-zinc-300">Parent:</dt>
                            <dd class="inline">{{ $role->parent?->name ?? 'None' }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-medium text-zinc-700 dark:text-zinc-300">Children:</dt>
                            <dd class="inline">
                                {{ $role->children->pluck('name')->join(', ') ?: 'None' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="font-medium">Permissions ({{ $role->permissions->count() }})</h2>
                    <ul class="mt-3 grid gap-1 text-sm text-zinc-600 dark:text-zinc-300 sm:grid-cols-2">
                        @forelse ($role->permissions->sortBy('name') as $permission)
                            <li>{{ $permission->name }}</li>
                        @empty
                            <li class="text-zinc-500">No direct permissions.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="font-medium">Assigned users ({{ $role->users->count() }})</h2>
                    <ul class="mt-3 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                        @forelse ($role->users as $user)
                            <li>{{ $user->name }} &lt;{{ $user->email }}&gt;</li>
                        @empty
                            <li class="text-zinc-500">No users assigned.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </main>
    </body>
</html>
