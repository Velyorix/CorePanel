<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>License activation — {{ config('corepanel.name') }}</title>
        <x-ui.vite-assets />
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="flex min-h-screen items-center justify-center px-4">
            <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ config('corepanel.name') }}</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Activate your license to finish installation</p>
                </div>

                <form method="POST" action="{{ route('install.license.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="license_key" class="mb-1.5 block text-sm font-medium">License key</label>
                        <input
                            id="license_key"
                            name="license_key"
                            type="text"
                            value="{{ old('license_key') }}"
                            required
                            autofocus
                            autocomplete="off"
                            class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:ring-zinc-800"
                        >
                        @error('license_key')
                            <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        Activate license
                    </button>
                </form>
            </div>
        </main>
    </body>
</html>
