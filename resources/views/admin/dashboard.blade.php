<x-layout.admin
    :title="__('Dashboard')"
    :page-heading="__('Dashboard')"
>
    <x-slot:subtitle>
        {{ __('Global overview of platform activity.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Dashboard')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6">
        <x-ui.alert variant="info" :title="__('Skeleton dashboard')">
            {{ __('KPI values and charts are placeholders.') }}
        </x-ui.alert>
    </div>

    <section aria-label="{{ __('Key performance indicators') }}" class="space-y-4">
        <h2 class="text-h3">{{ __('Overview') }}</h2>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <x-ui.card :title="$kpi['label']">
                    <p class="text-h1 font-semibold tracking-tight text-foreground" data-kpi="{{ $kpi['key'] }}">
                        {{ $kpi['value'] }}
                    </p>
                    <p class="mt-2 text-small text-muted-foreground">{{ $kpi['hint'] }}</p>
                    <div class="mt-4">
                        <x-ui.skeleton variant="line" class="w-1/2" />
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </section>

    <section aria-label="{{ __('Charts') }}" class="mt-10 space-y-4">
        <h2 class="text-h3">{{ __('Trends') }}</h2>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($charts as $chart)
                <x-ui.card :title="$chart['label']">
                    <p class="text-body-sm text-muted-foreground">{{ $chart['description'] }}</p>
                    <div
                        class="mt-6 flex h-40 items-end justify-between gap-2 rounded-md border border-dashed border-border bg-muted/40 px-4 py-3"
                        data-chart="{{ $chart['key'] }}"
                        role="img"
                        aria-label="{{ __('Chart placeholder for :label', ['label' => $chart['label']]) }}"
                    >
                        @foreach (range(1, 8) as $bar)
                            <x-ui.skeleton
                                variant="rect"
                                class="w-full"
                                style="height: {{ 30 + ($bar * 8) % 70 }}%"
                            />
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </section>
</x-layout.admin>
