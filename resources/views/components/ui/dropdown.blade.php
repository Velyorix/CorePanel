@props([
    'align' => 'left',
])

@php
    $menuAlign = $align === 'right' ? 'end-0' : 'start-0';
@endphp

<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="if (open) open = false"
    x-on:click.outside="open = false"
    {{ $attributes->class('relative inline-flex') }}
>
    <div x-on:click="open = ! open" x-on:keydown.enter.prevent="open = ! open" x-on:keydown.space.prevent="open = ! open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition
        role="menu"
        class="absolute {{ $menuAlign }} z-40 mt-2 min-w-44 overflow-hidden rounded-md border border-border bg-surface py-1 shadow-lg"
    >
        {{ $slot }}
    </div>
</div>
