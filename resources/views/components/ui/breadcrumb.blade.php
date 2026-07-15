@props([
    'items' => [],
])

@php
    /** @var list<array{label: string, url?: string|null}> $items */
    $trail = array_values($items);
@endphp

@if ($trail !== [])
    <nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-1.5 text-body-sm text-muted-foreground">
            @foreach ($trail as $index => $item)
                @php
                    $isLast = $index === array_key_last($trail);
                    $label = $item['label'] ?? '';
                    $url = $item['url'] ?? null;
                @endphp

                <li class="inline-flex items-center gap-1.5">
                    @if (! $isLast && filled($url))
                        <a
                            href="{{ $url }}"
                            class="font-medium transition hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            {{ $label }}
                        </a>
                        <span class="text-muted-foreground/70" aria-hidden="true">/</span>
                    @elseif (! $isLast)
                        <span>{{ $label }}</span>
                        <span class="text-muted-foreground/70" aria-hidden="true">/</span>
                    @else
                        <span class="font-medium text-foreground" aria-current="page">{{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
