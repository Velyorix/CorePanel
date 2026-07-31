@php
    $depth = $depth ?? 0;
    $hasChildren = ($item['children'] ?? []) !== [];
    $padding = $depth > 0 ? 'ps-6' : 'ps-3';
@endphp

@if ($item['placeholder'] && ! $hasChildren)
    <span
        class="{{ $padding }} block cursor-not-allowed rounded-md py-2 pe-3 text-body-sm text-muted-foreground/70"
        aria-disabled="true"
        title="{{ __('Coming soon') }}"
    >
        {{ $item['label'] }}
    </span>
@elseif ($item['placeholder'] && $hasChildren)
    <div class="space-y-0.5">
        <span class="{{ $padding }} block px-0 py-2 text-body-sm font-medium text-muted-foreground">
            {{ $item['label'] }}
        </span>
        <ul class="space-y-0.5">
            @foreach ($item['children'] as $child)
                <li>
                    @include('components.admin.partials.nav-item', ['item' => $child, 'depth' => $depth + 1])
                </li>
            @endforeach
        </ul>
    </div>
@else
    <div class="space-y-0.5">
        <a
            href="{{ $item['url'] }}"
            @class([
                $padding.' block rounded-md py-2 pe-3 text-body-sm font-medium transition',
                'bg-muted text-foreground' => $item['active'],
                'text-muted-foreground hover:bg-muted hover:text-foreground' => ! $item['active'],
            ])
            @if ($item['active']) aria-current="page" @endif
        >
            {{ $item['label'] }}
        </a>

        @if ($hasChildren)
            <ul class="space-y-0.5">
                @foreach ($item['children'] as $child)
                    <li>
                        @include('components.admin.partials.nav-item', ['item' => $child, 'depth' => $depth + 1])
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
