@props([
    'sort' => null,
    'dirParam' => 'dir',
    'sortParam' => 'sort',
])

@php
    $currentSort = request($sortParam);
    $currentDir = strtolower((string) request($dirParam, 'asc')) === 'desc' ? 'desc' : 'asc';
    $isActive = filled($sort) && $currentSort === $sort;
    $nextDir = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
    $href = filled($sort)
        ? request()->fullUrlWithQuery([
            $sortParam => $sort,
            $dirParam => $nextDir,
            'page' => null,
        ])
        : null;
@endphp

<th {{ $attributes->class('px-4 py-3 font-medium') }} @if ($isActive) aria-sort="{{ $currentDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    @if ($href)
        <a
            href="{{ $href }}"
            class="inline-flex items-center gap-1 text-muted-foreground transition hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <span>{{ $slot }}</span>
            <span class="text-small {{ $isActive ? 'text-primary-600' : 'text-muted-foreground/70' }}" aria-hidden="true">
                @if ($isActive && $currentDir === 'desc')
                    ↓
                @elseif ($isActive)
                    ↑
                @else
                    ↕
                @endif
            </span>
        </a>
    @else
        <span class="text-muted-foreground">{{ $slot }}</span>
    @endif
</th>
