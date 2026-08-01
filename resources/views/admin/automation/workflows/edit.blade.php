<x-layout.admin :title="__('Edit workflow')" :page-heading="__('Edit workflow')">
    <x-slot:subtitle>{{ $workflow->name }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Workflows'), 'url' => route('admin.automation.workflows.index')],
            ['label' => $workflow->name, 'url' => route('admin.automation.workflows.show', $workflow)],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Workflow details')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to save workflow')">
                    <ul class="list-disc ps-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.automation.workflows.update', $workflow) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.automation.workflows._form')
            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.automation.workflows.show', $workflow)" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ __('Save changes') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
