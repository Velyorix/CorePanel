<x-layout.admin
    :title="__('Node clusters')"
    :page-heading="__('Node clusters')"
>
    <x-slot:subtitle>
        {{ __('Group interconnected nodes for high-availability failover.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Infrastructure')],
            ['label' => __('Clusters')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.nodes.index')" variant="secondary" size="sm">
            {{ __('Servers') }}
        </x-ui.button>
        @can('create', Core\Nodes\Models\NodeCluster::class)
            <x-ui.button :href="route('admin.node-clusters.create')" variant="primary" size="sm">
                {{ __('Create cluster') }}
            </x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$clusters">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.node-clusters.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Name, key, location…')" />
                </div>
                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="name">{{ __('Name') }}</x-ui.table-heading>
                <x-ui.table-heading sort="key">{{ __('Key') }}</x-ui.table-heading>
                <x-ui.table-heading sort="location">{{ __('Location') }}</x-ui.table-heading>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Members') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="6" class="p-4">
                    <x-ui.empty :title="__('No clusters found')" :description="__('Create a cluster to interconnect HA peers.')" />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($clusters as $cluster)
            @php
                $statusVariant = $cluster->status === \Core\Nodes\Enums\NodeClusterStatus::Active ? 'success' : 'neutral';
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3 font-medium">{{ $cluster->name }}</td>
                <td class="px-4 py-3 font-mono text-small text-muted-foreground">{{ $cluster->key }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $cluster->location ?? '—' }}</td>
                <td class="px-4 py-3"><x-ui.badge :variant="$statusVariant">{{ $cluster->status->label() }}</x-ui.badge></td>
                <td class="px-4 py-3 text-muted-foreground">{{ $cluster->nodes_count }}</td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.node-clusters.show', $cluster)" variant="ghost" size="sm">{{ __('View') }}</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
