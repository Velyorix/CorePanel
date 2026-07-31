<x-layout.admin :title="$rule->name" :page-heading="$rule->name">
    <x-slot:subtitle>{{ __('Rule configuration and execution history.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Rules'), 'url' => route('admin.automation.rules.index')],
            ['label' => $rule->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button
            :href="route('admin.automation.logs.index', ['automation_rule_id' => $rule->id])"
            variant="secondary"
            size="sm"
        >{{ __('View logs') }}</x-ui.button>
        @can('update', $rule)
            <x-ui.button :href="route('admin.automation.rules.edit', $rule)" variant="secondary" size="sm">{{ __('Edit') }}</x-ui.button>
        @endcan
        @can('delete', $rule)
            <form method="POST" action="{{ route('admin.automation.rules.destroy', $rule) }}" onsubmit="return confirm(@js(__('Delete this rule?')))">
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
                        <x-ui.badge :variant="$rule->is_active ? 'success' : 'neutral'">
                            {{ $rule->is_active ? __('Active') : __('Inactive') }}
                        </x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Priority') }}</dt><dd>{{ $rule->priority }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Action') }}</dt><dd class="font-mono text-small">{{ $rule->action_json['type'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Runs') }}</dt><dd>{{ $rule->logs_count }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Conditions')">
            <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 font-mono text-small text-foreground">{{ json_encode($rule->condition_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-ui.card>

        <x-ui.card :title="__('Action')" class="lg:col-span-2">
            <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 font-mono text-small text-foreground">{{ json_encode($rule->action_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-ui.card>
    </div>
</x-layout.admin>
