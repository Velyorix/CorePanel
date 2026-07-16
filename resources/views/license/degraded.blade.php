<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>License required — {{ config('corepanel.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="flex min-h-screen items-center justify-center px-4">
            <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-6 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ config('corepanel.name') }}</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">License validation failed</p>
                </div>

                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                    {{ $message }}
                </p>

                @if (filled($state->reason))
                    <p class="mt-3 text-center text-xs text-zinc-500 dark:text-zinc-400">
                        Reason: {{ $state->reason }}
                    </p>
                @endif

                <div class="mt-6 flex flex-col gap-3">
                    @auth
                        <a
                            href="{{ route('admin.license.show') }}"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        >
                            Manage license in admin
                        </a>
                    @else
                        <a
                            href="{{ route('install.license.create') }}"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        >
                            Update license key
                        </a>
                    @endauth
                </div>
            </div>
        </main>
    </body>
</html>
