<div class="flex h-14 items-center border-b border-border px-4">
    <a
        href="{{ Route::has('client.dashboard') ? route('client.dashboard') : route('dashboard') }}"
        class="text-h3 font-semibold tracking-tight text-foreground hover:text-primary-600"
    >
        {{ config('corepanel.name') }}
        <span class="ms-1 text-small font-medium text-muted-foreground">{{ __('Client') }}</span>
    </a>
</div>

<div class="flex-1 overflow-y-auto p-3">
    {{ $sidebar ?? '' }}
</div>
