<x-layout.admin
    :title="__('Workflows')"
    :page-heading="__('Workflows')"
>
    <x-slot:subtitle>
        {{ __('Event-driven automation workflows with conditions and steps.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Automation')],
            ['label' => __('Workflows')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.automation.rules.index')" variant="secondary" size="sm">
            {{ __('Rules') }}
        </x-ui.button>
        <x-ui.button :href="route('admin.automation.logs.index')" variant="secondary" size="sm">
            {{ __('Logs') }}
        </x-ui.button>
        @can('create', Core\Automation\Models\Workflow::class)
            <x-ui.button :href="route('admin.automation.workflows.create')" variant="primary" size="sm">
                {{ __('Create workflow') }}
            </x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$workflows">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.automation.workflows.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Name, slug, event…')" />
                </div>
                <div class="min-w-48">
                    <x-ui.select name="trigger_event" :label="__('Event')">
                        <option value="">{{ __('All events') }}</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected(($filters['trigger_event'] ?? null) === $event)>
                                {{ \Core\Automation\Support\AutomationEvent::label($event) }}
                            </option>
                        @endforeach
                    </x-ui.select>
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
                <x-ui.table-heading sort="trigger_event">{{ __('Event') }}</x-ui.table-heading>
                <x-ui.table-heading sort="priority">{{ __('Priority') }}</x-ui.table-heading>
                <x-ui.table-heading sort="is_active">{{ __('Active') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Runs') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="6" class="p-4">
                    <x-ui.empty :title="__('No workflows found')" :description="__('Create a workflow to automate event handling.')" />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($workflows as $workflow)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $workflow->name }}</div>
                    <div class="font-mono text-small text-muted-foreground">{{ $workflow->slug }}</div>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $workflow->trigger_event }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $workflow->priority }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$workflow->is_active ? 'success' : 'neutral'">
                        {{ $workflow->is_active ? __('Active') : __('Inactive') }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $workflow->logs_count }}</td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.automation.workflows.show', $workflow)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
