@props([
    'title' => null,
    'pageHeading' => null,
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
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{ $head ?? '' }}
        @stack('head')
    </head>
    <body class="min-h-full bg-background font-sans text-body text-foreground antialiased">
        {{--
            Client area shell (CDC Tome 13 §4 + §10.2, Tome 7)
            Sidebar | Topbar | Breadcrumbs | Main
            Navigation menu = étape 6.2 · mobile drawer = 6.6
        --}}
        <div
            class="flex min-h-screen"
            x-data="{ sidebarOpen: false }"
            x-on:keydown.escape.window="sidebarOpen = false"
        >
            <aside
                class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface lg:flex"
                aria-label="{{ __('Client navigation') }}"
                data-client-sidebar-desktop
            >
                @include('components.client.partials.sidebar-shell')
            </aside>

            <div class="lg:hidden">
                <div
                    x-show="sidebarOpen"
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-0 z-40 bg-neutral-950/50"
                    x-on:click="sidebarOpen = false"
                    aria-hidden="true"
                    data-client-sidebar-overlay
                ></div>

                <aside
                    x-show="sidebarOpen"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                    class="fixed inset-y-0 start-0 z-50 flex w-64 max-w-[85vw] flex-col border-r border-border bg-surface shadow-xl"
                    aria-label="{{ __('Client navigation') }}"
                    role="dialog"
                    aria-modal="true"
                    data-client-sidebar-drawer
                    x-on:click="if ($event.target.closest('a[href]')) sidebarOpen = false"
                >
                    @include('components.client.partials.sidebar-shell', ['showClose' => true])
                </aside>
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-10 flex h-14 items-center gap-4 border-b border-border bg-surface px-4 sm:px-6"
                    aria-label="{{ __('Client top bar') }}"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring lg:hidden"
                            x-on:click="sidebarOpen = true"
                            aria-label="{{ __('Open client menu') }}"
                            x-bind:aria-expanded="sidebarOpen"
                            data-client-sidebar-toggle
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>

                        <span class="font-semibold tracking-tight text-foreground lg:hidden">
                            {{ config('corepanel.name') }}
                            <span class="text-muted-foreground">· {{ __('Client') }}</span>
                        </span>

                        {{ $topbar ?? '' }}
                    </div>
                </header>

                @isset($breadcrumbs)
                    <div class="border-b border-border bg-surface px-4 py-3 sm:px-6 lg:px-8">
                        {{ $breadcrumbs }}
                    </div>
                @endisset

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    @if (filled($pageHeading))
                        <div class="mb-6">
                            <h1>{{ $pageHeading }}</h1>
                            @isset($subtitle)
                                <div class="mt-2 text-muted-foreground">
                                    {{ $subtitle }}
                                </div>
                            @endisset
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        {{ $scripts ?? '' }}
        @stack('scripts')
    </body>
</html>
