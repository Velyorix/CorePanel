@php
    $statusVariant = $group->status === \Core\Nodes\Enums\NodeGroupStatus::Active ? 'success' : 'neutral';
@endphp

<x-layout.admin :title="$group->name" :page-heading="$group->name">
    <x-slot:subtitle>{{ __('Node group details and server assignments.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Groups'), 'url' => route('admin.node-groups.index')],
            ['label' => $group->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('update', $group)
            <x-ui.button :href="route('admin.node-groups.edit', $group)" variant="secondary" size="sm">{{ __('Edit') }}</x-ui.button>
        @endcan
        @can('delete', $group)
            <form method="POST" action="{{ route('admin.node-groups.destroy', $group) }}" onsubmit="return confirm(@js(__('Delete this node group?')))">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
            </form>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6"><x-ui.alert variant="success">{{ session('status') }}</x-ui.alert></div>
    @endif
    @if ($errors->has('group'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('group') }}</x-ui.alert></div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Status') }}</dt><dd><x-ui.badge :variant="$statusVariant">{{ $group->status->label() }}</x-ui.badge></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Key') }}</dt><dd class="font-mono text-small">{{ $group->key }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Location') }}</dt><dd>{{ $group->location ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Type') }}</dt><dd>{{ $group->type->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Products') }}</dt><dd>{{ $group->provisioning_rules_count }}</dd></div>
            </dl>
            @if (filled($group->description))
                <p class="mt-4 text-body-sm text-muted-foreground">{{ $group->description }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Assigned servers')">
            @if ($group->assignedNodes->isEmpty())
                <x-ui.empty :title="__('No servers assigned')" :description="__('Edit the group to assign servers.')" />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($group->assignedNodes as $node)
                        <li class="flex items-center justify-between gap-3 py-3 text-body-sm">
                            <div>
                                <div class="font-medium">{{ $node->name }}</div>
                                <div class="text-small text-muted-foreground">{{ $node->hostname }}</div>
                            </div>
                            @if ((bool) $node->pivot?->is_primary)
                                <x-ui.badge variant="neutral">{{ __('Primary') }}</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>
