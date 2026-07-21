<x-layout.admin :title="__('Create cluster')" :page-heading="__('Create cluster')">
    <x-slot:subtitle>{{ __('Define an HA cluster and assign peer nodes.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Clusters'), 'url' => route('admin.node-clusters.index')],
            ['label' => __('Create')],
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

        <form method="POST" action="{{ route('admin.node-clusters.store') }}" class="space-y-6">
            @csrf
            @include('admin.node-clusters._form')
            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.node-clusters.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ __('Create cluster') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
