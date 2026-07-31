<x-layout.admin :title="__('Create rule')" :page-heading="__('Create rule')">
    <x-slot:subtitle>{{ __('Define conditions and the action to run when they match.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Rules'), 'url' => route('admin.automation.rules.index')],
            ['label' => __('Create')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Rule details')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to save rule')">
                    <ul class="list-disc ps-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.automation.rules.store') }}" class="space-y-6">
            @csrf
            @include('admin.automation.rules._form')
            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.automation.rules.index')" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ __('Create rule') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
