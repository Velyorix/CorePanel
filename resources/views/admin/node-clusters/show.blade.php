@php
    $statusVariant = $cluster->status === \Core\Nodes\Enums\NodeClusterStatus::Active ? 'success' : 'neutral';
@endphp

<x-layout.admin :title="$cluster->name" :page-heading="$cluster->name">
    <x-slot:subtitle>{{ __('HA cluster details and peer assignments.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Clusters'), 'url' => route('admin.node-clusters.index')],
            ['label' => $cluster->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('update', $cluster)
            <x-ui.button :href="route('admin.node-clusters.edit', $cluster)" variant="secondary" size="sm">{{ __('Edit') }}</x-ui.button>
        @endcan
        @can('delete', $cluster)
            <form method="POST" action="{{ route('admin.node-clusters.destroy', $cluster) }}" onsubmit="return confirm(@js(__('Delete this node cluster?')))">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
            </form>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6"><x-ui.alert variant="success">{{ session('status') }}</x-ui.alert></div>
    @endif
    @if ($errors->has('cluster'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('cluster') }}</x-ui.alert></div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Status') }}</dt><dd><x-ui.badge :variant="$statusVariant">{{ $cluster->status->label() }}</x-ui.badge></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Key') }}</dt><dd class="font-mono text-small">{{ $cluster->key }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Location') }}</dt><dd>{{ $cluster->location ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted-foreground">{{ __('Members') }}</dt><dd>{{ $cluster->nodes_count }}</dd></div>
            </dl>
            @if (filled($cluster->description))
                <p class="mt-4 text-body-sm text-muted-foreground">{{ $cluster->description }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Cluster members')">
            @if ($cluster->nodes->isEmpty())
                <x-ui.empty :title="__('No members')" :description="__('Edit the cluster to assign peer nodes.')" />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($cluster->nodes as $node)
                        <li class="flex items-center justify-between gap-3 py-3 text-body-sm">
                            <div>
                                <div class="font-medium">{{ $node->name }}</div>
                                <div class="text-small text-muted-foreground">{{ $node->hostname }}</div>
                            </div>
                            <span class="text-small text-muted-foreground">
                                {{ __('Weight') }}: {{ $node->pivot?->weight ?? 100 }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>
