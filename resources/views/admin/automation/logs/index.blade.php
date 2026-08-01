<x-layout.admin
    :title="__('Automation logs')"
    :page-heading="__('Automation logs')"
>
    <x-slot:subtitle>
        {{ __('Execution history for workflows and rules.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Automation')],
            ['label' => __('Logs')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.automation.workflows.index')" variant="secondary" size="sm">
            {{ __('Workflows') }}
        </x-ui.button>
        <x-ui.button :href="route('admin.automation.rules.index')" variant="secondary" size="sm">
            {{ __('Rules') }}
        </x-ui.button>
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$logs">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.automation.logs.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Event, error, key…')" />
                </div>
                <div class="min-w-36">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="min-w-44">
                    <x-ui.select name="trigger_event" :label="__('Event')">
                        <option value="">{{ __('All events') }}</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected(($filters['trigger_event'] ?? null) === $event)>
                                {{ $event }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                @if (filled($filters['workflow_id'] ?? null))
                    <input type="hidden" name="workflow_id" value="{{ $filters['workflow_id'] }}">
                @endif
                @if (filled($filters['automation_rule_id'] ?? null))
                    <input type="hidden" name="automation_rule_id" value="{{ $filters['automation_rule_id'] }}">
                @endif
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="id">{{ __('ID') }}</x-ui.table-heading>
                <x-ui.table-heading sort="trigger_event">{{ __('Event') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Source') }}</th>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <x-ui.table-heading sort="attempt">{{ __('Attempt') }}</x-ui.table-heading>
                <x-ui.table-heading sort="created_at">{{ __('Created') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="7" class="p-4">
                    <x-ui.empty :title="__('No automation logs')" :description="__('Runs will appear here when workflows or rules execute.')" />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($logs as $entry)
            @php
                $variant = match ($entry->status) {
                    \Core\Automation\Enums\AutomationLogStatus::Succeeded => 'success',
                    \Core\Automation\Enums\AutomationLogStatus::Failed => 'danger',
                    \Core\Automation\Enums\AutomationLogStatus::Retrying,
                    \Core\Automation\Enums\AutomationLogStatus::Fallback => 'warning',
                    \Core\Automation\Enums\AutomationLogStatus::Running => 'primary',
                    default => 'neutral',
                };
                $source = $entry->workflow?->name
                    ?? $entry->automationRule?->name
                    ?? '—';
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3 font-mono text-small">{{ $entry->id }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $entry->trigger_event }}</td>
                <td class="px-4 py-3">{{ $source }}</td>
                <td class="px-4 py-3"><x-ui.badge :variant="$variant">{{ $entry->status->label() }}</x-ui.badge></td>
                <td class="px-4 py-3 text-muted-foreground">{{ $entry->attempt }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $entry->created_at?->toDateTimeString() }}</td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.automation.logs.show', $entry)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
