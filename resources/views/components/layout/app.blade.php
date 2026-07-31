@props([
    'title' => null,
])

@php
    $pageTitle = filled($title)
        ? $title.' — '.config('corepanel.name')
        : config('corepanel.name');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle }}</title>
        <x-ui.theme-script />
        <x-ui.vite-assets />
        {{ $head ?? '' }}
        @stack('head')
    </head>
    <body class="min-h-full bg-background font-sans text-body text-foreground antialiased">
        {{--
            CorePanel app shell — sidebar, topbar, main
            Full admin/client navigation is not wired here yet.
        --}}
        <div class="flex min-h-screen">
            <aside
                class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface lg:flex"
                aria-label="{{ __('Primary navigation') }}"
            >
                <div class="flex h-14 items-center border-b border-border px-4">
                    <a
                        href="{{ route('dashboard') }}"
                        class="text-h3 font-semibold tracking-tight text-foreground hover:text-primary-600"
                    >
                        {{ config('corepanel.name') }}
                    </a>
                </div>
                <div class="flex-1 overflow-y-auto p-3">
                    {{ $sidebar ?? '' }}
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-10 flex h-14 items-center gap-4 border-b border-border bg-surface px-4 sm:px-6"
                    aria-label="{{ __('Top bar') }}"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="font-semibold tracking-tight text-foreground lg:hidden">
                            {{ config('corepanel.name') }}
                        </span>
                        {{ $topbar ?? '' }}
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{ $scripts ?? '' }}
        @stack('scripts')
    </body>
</html>
