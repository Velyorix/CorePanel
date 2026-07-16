<x-layout.client
    :title="__('Dashboard')"
    :page-heading="__('Dashboard')"
>
    <x-slot:subtitle>
        {{ __('Quick overview of your services, billing, and support activity. Live metrics arrive in a later étape.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            <x-ui.theme-toggle />
            @auth
                <span class="hidden text-body-sm text-muted-foreground sm:inline">
                    {{ auth()->user()->email }}
                </span>
            @endauth
        </div>
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Client'), 'url' => route('client.dashboard')],
            ['label' => __('Dashboard')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6">
        <x-ui.alert variant="info" :title="__('Skeleton dashboard')">
            {{ __('KPI values are placeholders until services, billing, and support modules are connected.') }}
        </x-ui.alert>
    </div>

    @php
        $sections = [
            'services' => __('Services'),
            'billing' => __('Billing'),
            'support' => __('Support'),
        ];
    @endphp

    <div class="space-y-10">
        @foreach ($sections as $sectionKey => $sectionLabel)
            @php
                $sectionKpis = collect($kpis)->where('section', $sectionKey)->values();
            @endphp

            @if ($sectionKpis->isNotEmpty())
                <section aria-label="{{ $sectionLabel }}" class="space-y-4">
                    <h2 class="text-h3">{{ $sectionLabel }}</h2>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($sectionKpis as $kpi)
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
            @endif
        @endforeach
    </div>
</x-layout.client>
