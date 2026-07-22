<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Active sessions — {{ config('corepanel.name') }}</title>
        <x-ui.vite-assets />
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-screen max-w-3xl flex-col px-4 py-12">
            <div class="mb-8">
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-zinc-600 hover:underline dark:text-zinc-400">&larr; Back to dashboard</a>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight">Active sessions</h1>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    Manage devices where you are signed in. Revoke any session you do not recognize.
                </p>
            </div>

            @if (session('status'))
                <p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                    {{ session('status') }}
                </p>
            @endif

            <div class="mb-6 flex justify-end">
                <form method="POST" action="{{ route('account.sessions.revoke-others') }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    >
                        Revoke all other sessions
                    </button>
                </form>
            </div>

            <div class="space-y-4">
                @forelse ($sessions as $session)
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="font-medium">{{ $session->device_name ?? 'Unknown device' }}</h2>
                                    @if ($session->session_id === $currentSessionId)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            This device
                                        </span>
                                    @endif
                                </div>
                                <dl class="mt-3 space-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    <div>
                                        <dt class="inline font-medium text-zinc-700 dark:text-zinc-300">IP address:</dt>
                                        <dd class="inline">{{ $session->ip_address ?? 'Unknown' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="inline font-medium text-zinc-700 dark:text-zinc-300">Last activity:</dt>
                                        <dd class="inline">{{ $session->last_activity_at?->diffForHumans() ?? 'Unknown' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <form method="POST" action="{{ route('account.sessions.destroy', $session) }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                                >
                                    Revoke
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-zinc-200 bg-white p-5 text-sm text-zinc-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                        No active sessions found.
                    </p>
                @endforelse
            </div>
        </main>
    </body>
</html>
