<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verify email — {{ config('corepanel.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="flex min-h-screen items-center justify-center px-4">
            <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ config('corepanel.name') }}</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Verify your email address</p>
                </div>

                @if (session('status'))
                    <p class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ session('status') }}
                    </p>
                @endif

                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Thanks for signing up! Before getting started, please verify your email address by clicking the link we sent you.') }}
                </p>

                @error('email')
                    <p class="mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        Resend verification email
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-950"
                    >
                        Sign out
                    </button>
                </form>
            </div>
        </main>
    </body>
</html>
