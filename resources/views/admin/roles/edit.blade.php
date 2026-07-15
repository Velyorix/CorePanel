<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Edit {{ $role->name }} — {{ config('corepanel.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-screen max-w-4xl flex-col px-4 py-12">
            <div class="mb-8">
                <a href="{{ route('admin.roles.show', $role) }}" class="text-sm font-medium text-zinc-600 hover:underline dark:text-zinc-400">&larr; Back to role</a>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight">Edit role</h1>
            </div>

            <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                @csrf
                @method('PUT')
                @include('admin.roles._form')
                <div class="mt-8 flex justify-end gap-2">
                    <a href="{{ route('admin.roles.show', $role) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Cancel
                    </a>
                    <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                        Save changes
                    </button>
                </div>
            </form>
        </main>
    </body>
</html>
