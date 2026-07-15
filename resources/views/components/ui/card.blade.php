@props([
    'title' => null,
    'padding' => true,
])

<section {{ $attributes->class('rounded-lg border border-border bg-surface shadow-sm') }}>
    @if (isset($header) || filled($title))
        <header class="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
            <div class="min-w-0">
                @if (filled($title))
                    <h2 class="text-h3 font-semibold tracking-tight text-foreground">{{ $title }}</h2>
                @endif
                {{ $header ?? '' }}
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </header>
    @endif

    <div @class([
        'px-5 py-4' => $padding,
        'text-body-sm text-foreground' => true,
    ])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="border-t border-border px-5 py-4">
            {{ $footer }}
        </footer>
    @endisset
</section>
