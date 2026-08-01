<x-layout.admin
    :title="__('Automation rules')"
    :page-heading="__('Automation rules')"
>
    <x-slot:subtitle>
        {{ __('Conditional if/then rules evaluated on events or by the scheduler.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Automation')],
            ['label' => __('Rules')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.automation.workflows.index')" variant="secondary" size="sm">
            {{ __('Workflows') }}
        </x-ui.button>
        <x-ui.button :href="route('admin.automation.logs.index')" variant="secondary" size="sm">
            {{ __('Logs') }}
        </x-ui.button>
        @can('create', Core\Automation\Models\AutomationRule::class)
            <x-ui.button :href="route('admin.automation.rules.create')" variant="primary" size="sm">
                {{ __('Create rule') }}
            </x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$rules">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.automation.rules.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Rule name…')" />
                </div>
                <div class="min-w-36">
                    <x-ui.select name="is_active" :label="__('Status')">
                        <option value="">{{ __('All') }}</option>
                        <option value="1" @selected($filters['is_active'] === true)>{{ __('Active') }}</option>
                        <option value="0" @selected($filters['is_active'] === false)>{{ __('Inactive') }}</option>
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="name">{{ __('Name') }}</x-ui.table-heading>
                <x-ui.table-heading sort="priority">{{ __('Priority') }}</x-ui.table-heading>
                <x-ui.table-heading sort="is_active">{{ __('Active') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Action') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Runs') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="6" class="p-4">
                    <x-ui.empty :title="__('No rules found')" :description="__('Create a rule to automate conditional actions.')" />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($rules as $rule)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3 font-medium">{{ $rule->name }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $rule->priority }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$rule->is_active ? 'success' : 'neutral'">
                        {{ $rule->is_active ? __('Active') : __('Inactive') }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 font-mono text-small text-muted-foreground">{{ $rule->action_json['type'] ?? '—' }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $rule->logs_count }}</td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.automation.rules.show', $rule)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
