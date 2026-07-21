<x-layout.admin
    :title="__('Nodes')"
    :page-heading="__('Nodes')"
>
    <x-slot:subtitle>
        {{ __('Manage provisioning servers and infrastructure nodes.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Infrastructure')],
            ['label' => __('Nodes')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('create', Core\Nodes\Models\Node::class)
            <x-ui.button :href="route('admin.nodes.create')" variant="primary" size="sm">
                {{ __('Create server') }}
            </x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$nodes">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.nodes.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Name, hostname, module, IP…')"
                    />
                </div>

                <div class="min-w-40">
                    <x-ui.select name="type" :label="__('Type')">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type']?->value ?? null) === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-40">
                    <x-ui.select name="module" :label="__('Module')">
                        <option value="">{{ __('All modules') }}</option>
                        @foreach ($modules as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['module'] ?? null) === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-40">
                    <x-ui.select name="node_group_id" :label="__('Group')">
                        <option value="">{{ __('All groups') }}</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((string) ($filters['node_group_id'] ?? '') === (string) $group->id)>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                @if (filled(request('sort')))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif
                @if (filled(request('dir')))
                    <input type="hidden" name="dir" value="{{ request('dir') }}">
                @endif

                <x-ui.button type="submit" variant="secondary" size="sm">
                    {{ __('Apply') }}
                </x-ui.button>

                @if (filled($filters['q']) || $filters['type'] !== null || $filters['status'] !== null || filled($filters['module']) || $filters['node_group_id'] !== null)
                    <x-ui.button :href="route('admin.nodes.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="name">{{ __('Name') }}</x-ui.table-heading>
                <x-ui.table-heading sort="hostname">{{ __('Hostname') }}</x-ui.table-heading>
                <x-ui.table-heading sort="module">{{ __('Module') }}</x-ui.table-heading>
                <x-ui.table-heading sort="type">{{ __('Type') }}</x-ui.table-heading>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Credentials') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Load') }}</th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="7" class="p-4">
                    <x-ui.empty
                        :title="__('No servers found')"
                        :description="filled($filters['q']) || $filters['type'] !== null || $filters['status'] !== null || filled($filters['module']) || $filters['node_group_id'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Create a server to start provisioning services.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($nodes as $node)
            @php
                $statusVariant = match ($node->status) {
                    \Core\Nodes\Enums\NodeStatus::Active => 'success',
                    \Core\Nodes\Enums\NodeStatus::Maintenance => 'warning',
                    \Core\Nodes\Enums\NodeStatus::Offline => 'danger',
                    default => 'neutral',
                };
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $node->name }}</div>
                    <div class="text-small text-muted-foreground">#{{ $node->id }}</div>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $node->hostname }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $node->module ?? '—' }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $node->type->label() }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$statusVariant">
                        {{ $node->status->label() }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    @if ($node->hasConfiguredCredentials())
                        <x-ui.badge variant="success">{{ __('Configured') }}</x-ui.badge>
                    @else
                        <span class="text-muted-foreground">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ $node->allocated_services_count }}
                    @if ($node->max_services !== null)
                        / {{ $node->max_services }}
                    @endif
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
