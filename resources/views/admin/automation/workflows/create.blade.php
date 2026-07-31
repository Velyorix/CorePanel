<x-layout.admin :title="__('Create workflow')" :page-heading="__('Create workflow')">
    <x-slot:subtitle>{{ __('Define trigger, conditions, steps, and optional fallback.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Workflows'), 'url' => route('admin.automation.workflows.index')],
            ['label' => __('Create')],
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

        <form method="POST" action="{{ route('admin.automation.workflows.store') }}" class="space-y-6">
            @csrf
            @include('admin.automation.workflows._form')
            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.automation.workflows.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ __('Create workflow') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
