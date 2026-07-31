@php
    /** @var \Core\Sync\Models\SyncLog $log */
@endphp

<x-layout.admin
    :title="__('Sync log #:id', ['id' => $log->id])"
    :page-heading="__('Sync log #:id', ['id' => $log->id])"
>
    <x-slot:subtitle>
        {{ __('Detailed sync operation record.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Sync history'), 'url' => route('admin.sync-logs.index')],
            ['label' => '#'.$log->id],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('admin.sync-logs.index')" variant="secondary" size="sm">
            {{ __('Back to sync history') }}
        </x-ui.button>

        @if ($url = $log->subjectUrl())
            <x-ui.button :href="$url" variant="ghost" size="sm">
                {{ __('Open resource') }}
            </x-ui.button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('When') }}</dt>
                    <dd>{{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Subject') }}</dt>
                    <dd>{{ $log->subject_type->label() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Resource') }}</dt>
                    <dd>{{ $log->subjectLabel() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Module') }}</dt>
                    <dd>{{ $log->module ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Outcome') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$log->outcome->badgeVariant()">{{ $log->outcome->label() }}</x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Message') }}</dt>
                    <dd class="text-end">{{ $log->message ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Divergences')">
            @if (filled($log->divergences))
                <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 text-small">{{ json_encode($log->divergences, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="text-body-sm text-muted-foreground">{{ __('No divergences recorded for this run.') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Resolutions')">
            @if (filled($log->resolutions))
                <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 text-small">{{ json_encode($log->resolutions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="text-body-sm text-muted-foreground">{{ __('No automatic resolutions were applied.') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Payload')">
            @if (filled($log->payload))
                <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 text-small">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="text-body-sm text-muted-foreground">{{ __('No additional payload recorded.') }}</p>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>
