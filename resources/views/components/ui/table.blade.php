@props([
    'paginator' => null,
])

<div {{ $attributes->class('w-full') }}>
    @isset($filters)
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            {{ $filters }}
        </div>
    @endisset

    @isset($actions)
        <div class="mb-3 flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset

    <div class="overflow-x-auto rounded-lg border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-start text-body-sm">
            @isset($head)
                <thead class="bg-muted/60 text-small font-medium uppercase tracking-wide text-muted-foreground">
                    {{ $head }}
                </thead>
            @endisset

            <tbody class="divide-y divide-border bg-surface text-foreground">
                @if (isset($empty) && $paginator !== null && $paginator->total() === 0)
                    {{ $empty }}
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="mt-4">
            {{ $footer }}
        </div>
    @elseif ($paginator !== null)
        <div class="mt-4">
            {{ $paginator->links('components.ui.pagination') }}
        </div>
    @endif
</div>
