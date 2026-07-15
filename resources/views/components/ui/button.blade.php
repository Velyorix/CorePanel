@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'border-transparent bg-primary-600 text-white hover:bg-primary-700 focus-visible:outline-primary-600 disabled:bg-primary-600/50',
        'secondary' => 'border-border bg-surface text-foreground hover:bg-muted focus-visible:outline-ring disabled:opacity-50',
        'danger' => 'border-transparent bg-danger-600 text-white hover:bg-danger-700 focus-visible:outline-danger-600 disabled:bg-danger-600/50',
        'ghost' => 'border-transparent bg-transparent text-foreground hover:bg-muted focus-visible:outline-ring disabled:opacity-50',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-small',
        'md' => 'px-3.5 py-2 text-body-sm',
        'lg' => 'px-4 py-2.5 text-body',
    ];

    $classes = trim(implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-md border font-medium transition',
        'focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:pointer-events-none disabled:cursor-not-allowed',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]));
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        {{ $attributes->class($classes) }}
        @if ($attributes->has('disabled') || $attributes->get('aria-disabled') === 'true')
            aria-disabled="true"
            tabindex="-1"
        @endif
    >
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class($classes) }}
    >
        {{ $slot }}
    </button>
@endif
