@php
    /** @var \Core\Nodes\DataTransferObjects\NodeMonitoringDashboard $dashboard */
@endphp

<x-layout.admin
    :title="__('Node monitoring')"
    :page-heading="__('Node monitoring')"
>
    <x-slot:subtitle>
        {{ __('Real-time infrastructure metrics across your node fleet.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Infrastructure')],
            ['label' => __('Nodes'), 'url' => route('admin.nodes.index')],
            ['label' => __('Monitoring')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('admin.nodes.index')" variant="secondary" size="sm">
            {{ __('Back to nodes') }}
        </x-ui.button>

        <p class="text-small text-muted-foreground">
            {{ __('Showing the last :hours hours of collected metrics.', ['hours' => $dashboard->historyHours]) }}
        </p>
    </div>

    <section aria-label="{{ __('Fleet overview') }}" class="space-y-4">
        <h2 class="text-h3">{{ __('Overview') }}</h2>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.card :title="__('Total nodes')">
                <p class="text-h1 font-semibold tracking-tight">{{ $dashboard->totalNodes }}</p>
            </x-ui.card>

            <x-ui.card :title="__('Online')">
                <p class="text-h1 font-semibold tracking-tight text-success-700 dark:text-success-400">{{ $dashboard->onlineCount }}</p>
            </x-ui.card>

            <x-ui.card :title="__('Degraded')">
                <p class="text-h1 font-semibold tracking-tight text-warning-700 dark:text-warning-400">{{ $dashboard->degradedCount }}</p>
            </x-ui.card>

            <x-ui.card :title="__('Offline')">
                <p class="text-h1 font-semibold tracking-tight text-danger-700 dark:text-danger-400">{{ $dashboard->offlineCount }}</p>
            </x-ui.card>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.card :title="__('Average CPU')">
                <p class="text-h2 font-semibold tracking-tight">
                    {{ $dashboard->averageCpu !== null ? number_format($dashboard->averageCpu, 1).'%' : '—' }}
                </p>
            </x-ui.card>

            <x-ui.card :title="__('Average RAM')">
                <p class="text-h2 font-semibold tracking-tight">
                    {{ $dashboard->averageRamMb !== null ? number_format($dashboard->averageRamMb, 0).' MB' : '—' }}
                </p>
            </x-ui.card>
        </div>
    </section>

    <section aria-label="{{ __('Fleet trends') }}" class="mt-10 space-y-4">
        <h2 class="text-h3">{{ __('Trends') }}</h2>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($dashboard->aggregateCharts as $chart)
                <x-ui.card :title="$chart->label">
                    <x-ui.metric-chart :series="$chart" />
                </x-ui.card>
            @endforeach
        </div>
    </section>

    <section aria-label="{{ __('Node metrics') }}" class="mt-10 space-y-4">
        <h2 class="text-h3">{{ __('Nodes') }}</h2>

        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Health') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Uptime') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('CPU') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('RAM') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Load') }}</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </x-slot:head>

            <x-slot:empty>
                <tr>
                    <td colspan="7" class="p-4">
                        <x-ui.empty
                            :title="__('No nodes found')"
                            :description="__('Create a node to start collecting infrastructure metrics.')"
                        />
                    </td>
                </tr>
            </x-slot:empty>

            @foreach ($dashboard->nodes as $summary)
                @php
                    $node = $summary->node;
                    $healthState = $node->healthState();
                    $healthVariant = match ($healthState) {
                        \Core\Nodes\Enums\NodeHealthState::Online => 'success',
                        \Core\Nodes\Enums\NodeHealthState::Degraded => 'warning',
                        \Core\Nodes\Enums\NodeHealthState::Offline => 'danger',
                        default => 'neutral',
                    };
                @endphp
                <tr class="hover:bg-muted/40">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $node->name }}</div>
                        <div class="text-small text-muted-foreground">{{ $node->hostname }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$healthVariant">{{ $healthState->label() }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                        {{ $summary->uptimePercent !== null ? number_format($summary->uptimePercent, 1).'%' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                        {{ $summary->latestCpu !== null ? number_format($summary->latestCpu, 1).'%' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                        {{ $summary->latestRamMb !== null ? number_format($summary->latestRamMb, 0).' MB' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                        {{ $summary->latestLoad !== null ? number_format($summary->latestLoad, 2) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-end">
                        @can('view', $node)
                            <x-ui.button :href="route('admin.nodes.show', $node)" variant="ghost" size="sm">
                                {{ __('View') }}
                            </x-ui.button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </section>
</x-layout.admin>
