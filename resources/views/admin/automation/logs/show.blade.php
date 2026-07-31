@php
    $variant = match ($log->status) {
        \Core\Automation\Enums\AutomationLogStatus::Succeeded => 'success',
        \Core\Automation\Enums\AutomationLogStatus::Failed => 'danger',
        \Core\Automation\Enums\AutomationLogStatus::Retrying,
        \Core\Automation\Enums\AutomationLogStatus::Fallback => 'warning',
        \Core\Automation\Enums\AutomationLogStatus::Running => 'primary',
        default => 'neutral',
    };
@endphp

<x-layout.admin :title="__('Automation run #:id', ['id' => $log->id])" :page-heading="__('Run #:id', ['id' => $log->id])">
    <x-slot:subtitle>{{ $log->trigger_event }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Logs'), 'url' => route('admin.automation.logs.index')],
            ['label' => '#'.$log->id],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('retry', $log)
            @if ($log->status === \Core\Automation\Enums\AutomationLogStatus::Retrying)
                <form method="POST" action="{{ route('admin.automation.logs.retry', $log) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">{{ __('Retry now') }}</x-ui.button>
                </form>
            @endif
        @endcan
        <x-ui.button :href="route('admin.automation.logs.index')" variant="secondary" size="sm">{{ __('Back') }}</x-ui.button>
    </div>

    @if (session('status'))
        <div class="mb-6"><x-ui.alert variant="success">{{ session('status') }}</x-ui.alert></div>
    @endif
    @if ($errors->has('log'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('log') }}</x-ui.alert></div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd><x-ui.badge :variant="$variant">{{ $log->status->label() }}</x-ui.badge></dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Event') }}</dt><dd>{{ $log->trigger_event }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Attempt') }}</dt><dd>{{ $log->attempt }}</dd></div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Workflow') }}</dt>
                    <dd>
                        @if ($log->workflow)
                            <a class="text-primary underline-offset-2 hover:underline" href="{{ route('admin.automation.workflows.show', $log->workflow) }}">
                                {{ $log->workflow->name }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Rule') }}</dt>
                    <dd>
                        @if ($log->automationRule)
                            <a class="text-primary underline-offset-2 hover:underline" href="{{ route('admin.automation.rules.show', $log->automationRule) }}">
                                {{ $log->automationRule->name }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Started') }}</dt><dd>{{ $log->started_at?->toDateTimeString() ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Finished') }}</dt><dd>{{ $log->finished_at?->toDateTimeString() ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Next retry') }}</dt><dd>{{ $log->next_retry_at?->toDateTimeString() ?? '—' }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Idempotency')">
            <p class="break-all font-mono text-small text-muted-foreground">{{ $log->idempotency_key ?? '—' }}</p>
            @if (filled($log->error_message))
                <div class="mt-4">
                    <p class="mb-1 text-body-sm font-medium text-foreground">{{ __('Error') }}</p>
                    <p class="text-body-sm text-danger-700 dark:text-danger-400">{{ $log->error_message }}</p>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Payload')" class="lg:col-span-2">
            <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 font-mono text-small text-foreground">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' }}</pre>
        </x-ui.card>

        <x-ui.card :title="__('Result')" class="lg:col-span-2">
            <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 font-mono text-small text-foreground">{{ json_encode($log->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' }}</pre>
        </x-ui.card>
    </div>
</x-layout.admin>
