<div class="flex h-14 items-center justify-between gap-3 border-b border-border px-4">
    <a
        href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : route('dashboard') }}"
        class="text-h3 font-semibold tracking-tight text-foreground hover:text-primary-600"
    >
        {{ config('corepanel.name') }}
        <span class="ms-1 text-small font-medium text-muted-foreground">{{ __('Admin') }}</span>
    </a>

    @if (! empty($showClose))
        <button
            type="button"
            class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            x-on:click="sidebarOpen = false"
            aria-label="{{ __('Close admin menu') }}"
            data-admin-sidebar-close
        >
            <span class="text-h3 leading-none" aria-hidden="true">&times;</span>
        </button>
    @endif
</div>

<div class="flex-1 overflow-y-auto p-3">
    @isset($sidebar)
        {{ $sidebar }}
    @else
        <x-admin.nav />
    @endisset
</div>
