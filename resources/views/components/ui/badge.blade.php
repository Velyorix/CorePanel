@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'bg-muted text-muted-foreground ring-border',
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-200 dark:bg-primary-950 dark:text-primary-300 dark:ring-primary-900',
        'success' => 'bg-success-50 text-success-700 ring-success-200 dark:bg-success-950 dark:text-success-300 dark:ring-success-900',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-200 dark:bg-warning-950 dark:text-warning-300 dark:ring-warning-900',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-200 dark:bg-danger-950 dark:text-danger-300 dark:ring-danger-900',
    ];
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center rounded-md px-2 py-0.5 text-small font-medium ring-1 ring-inset',
        $variants[$variant] ?? $variants['neutral'],
    ]) }}
>
    {{ $slot }}
</span>
