@props([
    'title' => null,
    'description' => null,
])

<div
    {{ $attributes->class('flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-surface px-6 py-12 text-center') }}
    role="status"
>
    @isset($icon)
        <div class="mb-4 text-muted-foreground">
            {{ $icon }}
        </div>
    @else
        <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground" aria-hidden="true">
            <span class="text-h3 font-semibold">∅</span>
        </div>
    @endisset

    @if (filled($title))
        <h3 class="text-h3 font-semibold tracking-tight text-foreground">{{ $title }}</h3>
    @endif

    @if (filled($description))
        <p class="mt-2 max-w-md text-body-sm text-muted-foreground">{{ $description }}</p>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="mt-2 max-w-md text-body-sm text-muted-foreground">
            {{ $slot }}
        </div>
    @endif

    @isset($actions)
        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
