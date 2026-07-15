@props([
    'href' => null,
    'type' => 'button',
])

@php
    $classes = 'block w-full px-3 py-2 text-start text-body-sm text-foreground transition hover:bg-muted focus-visible:bg-muted focus-visible:outline-none';
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($classes) }} x-on:click="open = false">
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" role="menuitem" {{ $attributes->class($classes) }} x-on:click="open = false">
        {{ $slot }}
    </button>
@endif
