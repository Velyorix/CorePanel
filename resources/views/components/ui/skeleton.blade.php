@props([
    'variant' => 'line',
    'lines' => 1,
])

@php
    $variants = [
        'line' => 'h-3 w-full rounded-md',
        'text' => 'h-4 w-3/4 rounded-md',
        'circle' => 'size-10 shrink-0 rounded-full',
        'rect' => 'h-24 w-full rounded-lg',
        'avatar' => 'size-12 shrink-0 rounded-full',
        'button' => 'h-9 w-24 rounded-md',
    ];

    $base = 'animate-pulse bg-muted';
    $shape = $variants[$variant] ?? $variants['line'];
    $lineCount = max(1, (int) $lines);
@endphp

@if ($variant === 'line' && $lineCount > 1)
    <div {{ $attributes->class('space-y-2') }} role="status" aria-label="{{ __('Loading') }}">
        @for ($i = 0; $i < $lineCount; $i++)
            <div @class([
                $base,
                $shape,
                'w-full' => $i < $lineCount - 1,
                'w-2/3' => $i === $lineCount - 1,
            ])></div>
        @endfor
        <span class="sr-only">{{ __('Loading') }}</span>
    </div>
@else
    <div
        {{ $attributes->class([$base, $shape]) }}
        role="status"
        aria-label="{{ __('Loading') }}"
    >
        <span class="sr-only">{{ __('Loading') }}</span>
    </div>
@endif
