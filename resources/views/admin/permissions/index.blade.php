<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Permissions — {{ config('corepanel.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-screen max-w-5xl flex-col px-4 py-12">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm font-medium text-zinc-600 hover:underline dark:text-zinc-400">&larr; Back to roles</a>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight">Permissions</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Catalog of permissions available for role assignment.
                    </p>
                </div>
            </div>

            <div class="space-y-6">
                @foreach ($permissions as $module => $modulePermissions)
                    <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">{{ $module }}</h2>
                        <ul class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($modulePermissions as $permission)
                                <li class="flex items-start justify-between gap-4 py-2 text-sm">
                                    <div>
                                        <div class="font-medium">{{ $permission->name }}</div>
                                        @if ($permission->description)
                                            <div class="text-zinc-500">{{ $permission->description }}</div>
                                        @endif
                                    </div>
                                    <div class="shrink-0 text-zinc-500">{{ $permission->roles_count }} roles</div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </main>
    </body>
</html>
