<x-layout.admin :title="$workflow->name" :page-heading="$workflow->name">
    <x-slot:subtitle>{{ __('Workflow configuration and recent activity.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Workflows'), 'url' => route('admin.automation.workflows.index')],
            ['label' => $workflow->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button
            :href="route('admin.automation.logs.index', ['workflow_id' => $workflow->id])"
            variant="secondary"
            size="sm"
        >{{ __('View logs') }}</x-ui.button>
        @can('update', $workflow)
            <x-ui.button :href="route('admin.automation.workflows.edit', $workflow)" variant="secondary" size="sm">{{ __('Edit') }}</x-ui.button>
        @endcan
        @can('delete', $workflow)
            <form method="POST" action="{{ route('admin.automation.workflows.destroy', $workflow) }}" onsubmit="return confirm(@js(__('Delete this workflow?')))">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
            </form>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6"><x-ui.alert variant="success">{{ session('status') }}</x-ui.alert></div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$workflow->is_active ? 'success' : 'neutral'">
                            {{ $workflow->is_active ? __('Active') : __('Inactive') }}
                        </x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Slug') }}</dt><dd class="font-mono text-small">{{ $workflow->slug }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Event') }}</dt><dd>{{ $workflow->trigger_event }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Priority') }}</dt><dd>{{ $workflow->priority }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Runs') }}</dt><dd>{{ $workflow->logs_count }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Conditions')">
            <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 font-mono text-small text-foreground">{{ json_encode($workflow->conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' }}</pre>
        </x-ui.card>

        <x-ui.card :title="__('Steps')" class="lg:col-span-2">
            <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 font-mono text-small text-foreground">{{ json_encode($workflow->steps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-ui.card>

        @if ($workflow->fallback)
            <x-ui.card :title="__('Fallback')" class="lg:col-span-2">
                <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 font-mono text-small text-foreground">{{ json_encode($workflow->fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </x-ui.card>
        @endif
    </div>
</x-layout.admin>
