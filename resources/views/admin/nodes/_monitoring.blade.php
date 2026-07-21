@php
    /** @var \Core\Nodes\DataTransferObjects\NodeMonitoringView $monitoring */
@endphp

<x-ui.card :title="__('Monitoring')" class="lg:col-span-2">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-small text-muted-foreground">
            {{ __('Last :hours hours of metrics.', ['hours' => $monitoring->historyHours]) }}
        </p>

        @can('viewAny', Core\Nodes\Models\Node::class)
            <x-ui.button :href="route('admin.nodes.monitoring')" variant="ghost" size="sm">
                {{ __('Fleet dashboard') }}
            </x-ui.button>
        @endcan
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
            <p class="text-small text-muted-foreground">{{ __('Uptime') }}</p>
            <p class="text-h3 font-semibold">
                {{ $monitoring->uptimePercent !== null ? number_format($monitoring->uptimePercent, 1).'%' : '—' }}
            </p>
        </div>
        <div>
            <p class="text-small text-muted-foreground">{{ __('CPU') }}</p>
            <p class="text-h3 font-semibold">
                {{ $monitoring->latestCpu !== null ? number_format($monitoring->latestCpu, 1).'%' : '—' }}
            </p>
        </div>
        <div>
            <p class="text-small text-muted-foreground">{{ __('RAM') }}</p>
            <p class="text-h3 font-semibold">
                {{ $monitoring->latestRamMb !== null ? number_format($monitoring->latestRamMb, 0).' MB' : '—' }}
            </p>
        </div>
        <div>
            <p class="text-small text-muted-foreground">{{ __('Load') }}</p>
            <p class="text-h3 font-semibold">
                {{ $monitoring->latestLoad !== null ? number_format($monitoring->latestLoad, 2) : '—' }}
            </p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($monitoring->charts as $chart)
            <x-ui.metric-chart :series="$chart" />
        @endforeach
    </div>
</x-ui.card>
