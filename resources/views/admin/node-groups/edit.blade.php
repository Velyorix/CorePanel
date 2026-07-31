<x-layout.admin :title="__('Edit group')" :page-heading="__('Edit group')">
    <x-slot:subtitle>{{ $group->name }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Groups'), 'url' => route('admin.node-groups.index')],
            ['label' => $group->name, 'url' => route('admin.node-groups.show', $group)],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Group details')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to save group')">
                    <ul class="list-disc ps-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.node-groups.update', $group) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.node-groups._form', ['group' => $group])
            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.node-groups.show', $group)" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ __('Save changes') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
