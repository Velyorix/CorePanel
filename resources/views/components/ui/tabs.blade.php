@props([
    'items' => [],
    'active' => null,
])

@php
    /** @var list<array{name: string, label: string}> $items */
    $activeTab = $active ?? ($items[0]['name'] ?? null);
@endphp

<div
    x-data="{ tab: @js($activeTab) }"
    {{ $attributes->class('w-full') }}
>
    <div
        role="tablist"
        aria-orientation="horizontal"
        class="flex flex-wrap gap-1 border-b border-border"
    >
        @foreach ($items as $item)
            <button
                type="button"
                role="tab"
                id="tab-{{ $item['name'] }}"
                x-bind:aria-selected="tab === @js($item['name'])"
                x-bind:tabindex="tab === @js($item['name']) ? 0 : -1"
                x-bind:class="tab === @js($item['name'])
                    ? 'border-primary-600 text-primary-700 dark:text-primary-300'
                    : 'border-transparent text-muted-foreground hover:text-foreground'"
                class="border-b-2 px-3 py-2 text-body-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                x-on:click="tab = @js($item['name'])"
            >
                {{ $item['label'] }}
            </button>
        @endforeach
    </div>

    <div class="pt-4">
        @foreach ($items as $item)
            @php
                $panel = $item['name'];
            @endphp
            <div
                x-show="tab === @js($item['name'])"
                x-cloak
                role="tabpanel"
                aria-labelledby="tab-{{ $item['name'] }}"
                class="text-body-sm text-foreground"
            >
                {{ $$panel ?? '' }}
            </div>
        @endforeach
    </div>
</div>
