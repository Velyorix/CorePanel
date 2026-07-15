@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $variants = [
        'info' => [
            'container' => 'border-primary-200 bg-primary-50 text-primary-900 dark:border-primary-900 dark:bg-primary-950 dark:text-primary-100',
            'title' => 'text-primary-950 dark:text-primary-50',
        ],
        'success' => [
            'container' => 'border-success-200 bg-success-50 text-success-900 dark:border-success-900 dark:bg-success-950 dark:text-success-100',
            'title' => 'text-success-950 dark:text-success-50',
        ],
        'warning' => [
            'container' => 'border-warning-200 bg-warning-50 text-warning-900 dark:border-warning-900 dark:bg-warning-950 dark:text-warning-100',
            'title' => 'text-warning-950 dark:text-warning-50',
        ],
        'danger' => [
            'container' => 'border-danger-200 bg-danger-50 text-danger-900 dark:border-danger-900 dark:bg-danger-950 dark:text-danger-100',
            'title' => 'text-danger-950 dark:text-danger-50',
        ],
    ];

    $style = $variants[$variant] ?? $variants['info'];
@endphp

<div
    role="alert"
    {{ $attributes->class([
        'rounded-lg border px-4 py-3 text-body-sm',
        $style['container'],
    ]) }}
>
    @if (filled($title))
        <p class="font-semibold {{ $style['title'] }}">{{ $title }}</p>
        @if (trim((string) $slot) !== '')
            <div class="mt-1 opacity-90">{{ $slot }}</div>
        @endif
    @else
        {{ $slot }}
    @endif
</div>
