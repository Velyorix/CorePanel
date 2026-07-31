@props([
    'variant' => 'buttons',
])

<div
    x-data="themeToggle"
    {{ $attributes->class('inline-flex items-center') }}
    role="group"
    aria-label="{{ __('Theme') }}"
>
    @if ($variant === 'cycle')
        <x-ui.button
            type="button"
            variant="secondary"
            size="sm"
            x-on:click="cycle()"
            x-bind:aria-label="
                preference === 'light' ? @js(__('Light theme')) :
                preference === 'dark' ? @js(__('Dark theme')) :
                @js(__('System theme'))
            "
        >
            <span x-show="preference === 'light'" x-cloak>{{ __('Light') }}</span>
            <span x-show="preference === 'dark'" x-cloak>{{ __('Dark') }}</span>
            <span x-show="preference === 'system'" x-cloak>{{ __('System') }}</span>
        </x-ui.button>
    @else
        <div class="inline-flex overflow-hidden rounded-md border border-border bg-surface p-0.5">
            <button
                type="button"
                class="rounded-sm px-2.5 py-1 text-small font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                x-on:click="set('light')"
                x-bind:class="preference === 'light' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                x-bind:aria-pressed="preference === 'light'"
            >
                {{ __('Light') }}
            </button>
            <button
                type="button"
                class="rounded-sm px-2.5 py-1 text-small font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                x-on:click="set('dark')"
                x-bind:class="preference === 'dark' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                x-bind:aria-pressed="preference === 'dark'"
            >
                {{ __('Dark') }}
            </button>
            <button
                type="button"
                class="rounded-sm px-2.5 py-1 text-small font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                x-on:click="set('system')"
                x-bind:class="preference === 'system' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'"
                x-bind:aria-pressed="preference === 'system'"
            >
                {{ __('System') }}
            </button>
        </div>
    @endif
</div>
