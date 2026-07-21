<x-layout.admin :title="__('Edit cluster')" :page-heading="__('Edit cluster')">
    <x-slot:subtitle>{{ $cluster->name }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Clusters'), 'url' => route('admin.node-clusters.index')],
            ['label' => $cluster->name, 'url' => route('admin.node-clusters.show', $cluster)],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Cluster details')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to save cluster')">
                    <ul class="list-disc ps-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.node-clusters.update', $cluster) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.node-clusters._form', ['cluster' => $cluster])
            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.node-clusters.show', $cluster)" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ __('Save changes') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
