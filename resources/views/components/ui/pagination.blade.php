@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-body-sm text-muted-foreground">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span class="font-medium text-foreground">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-medium text-foreground">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-medium text-foreground">{{ $paginator->count() }}</span>
            @endif
            {!! __('of') !!}
            <span class="font-medium text-foreground">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex cursor-not-allowed items-center rounded-md border border-border bg-surface px-3 py-1.5 text-body-sm text-muted-foreground opacity-60">
                    {{ __('Previous') }}
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    class="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-body-sm font-medium text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    {{ __('Previous') }}
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex items-center px-2 text-body-sm text-muted-foreground">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span
                                aria-current="page"
                                class="inline-flex items-center rounded-md border border-primary-600 bg-primary-600 px-3 py-1.5 text-body-sm font-medium text-white"
                            >
                                {{ $page }}
                            </span>
                        @else
                            <a
                                href="{{ $url }}"
                                class="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-body-sm font-medium text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    class="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-body-sm font-medium text-foreground transition hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    {{ __('Next') }}
                </a>
            @else
                <span class="inline-flex cursor-not-allowed items-center rounded-md border border-border bg-surface px-3 py-1.5 text-body-sm text-muted-foreground opacity-60">
                    {{ __('Next') }}
                </span>
            @endif
        </div>
    </nav>
@endif
