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
            Admin panel shell (CDC Tome 13 §4 + §9.2, Tome 6)
            Sidebar | Topbar | Breadcrumbs | Main
            Full navigation menu = étape 5.2 · mobile drawer = 5.7
        --}}
        <div class="flex min-h-screen">
            <aside
                class="hidden w-72 shrink-0 flex-col border-r border-border bg-surface lg:flex"
                aria-label="{{ __('Admin navigation') }}"
            >
                <div class="flex h-14 items-center border-b border-border px-4">
                    <a
                        href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : route('dashboard') }}"
                        class="text-h3 font-semibold tracking-tight text-foreground hover:text-primary-600"
                    >
                        {{ config('corepanel.name') }}
                        <span class="ms-1 text-small font-medium text-muted-foreground">{{ __('Admin') }}</span>
                    </a>
                </div>
                <div class="flex-1 overflow-y-auto p-3">
                    @isset($sidebar)
                        {{ $sidebar }}
                    @else
                        <x-admin.nav />
                    @endisset
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-10 flex h-14 items-center gap-4 border-b border-border bg-surface px-4 sm:px-6"
                    aria-label="{{ __('Admin top bar') }}"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="font-semibold tracking-tight text-foreground lg:hidden">
                            {{ config('corepanel.name') }}
                            <span class="text-muted-foreground">· {{ __('Admin') }}</span>
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
