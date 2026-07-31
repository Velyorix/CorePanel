@props([
    'name',
    'title' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
    ];
    $maxWidth = $sizes[$size] ?? $sizes['md'];
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name)) { open = true }"
    x-on:close-modal.window="if ($event.detail === @js($name) || $event.detail === null) { open = false }"
    x-on:keydown.escape.window="if (open) open = false"
    {{ $attributes->except('class') }}
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        @if (filled($title)) aria-labelledby="modal-{{ $name }}-title" @endif
    >
        <div
            x-show="open"
            x-transition.opacity
            class="absolute inset-0 bg-neutral-950/50"
            x-on:click="open = false"
            aria-hidden="true"
        ></div>

        <div
            x-show="open"
            x-transition
            x-on:click.stop
            class="relative z-10 w-full {{ $maxWidth }} overflow-hidden rounded-lg border border-border bg-surface shadow-lg"
        >
            <div class="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
                <div class="min-w-0">
                    @if (filled($title))
                        <h2 id="modal-{{ $name }}-title" class="text-h3 font-semibold tracking-tight text-foreground">
                            {{ $title }}
                        </h2>
                    @endif
                    {{ $header ?? '' }}
                </div>
                <button
                    type="button"
                    class="rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    x-on:click="open = false"
                    aria-label="{{ __('Close') }}"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="px-5 py-4 text-body-sm text-foreground">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
