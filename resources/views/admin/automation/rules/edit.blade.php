<x-layout.admin :title="__('Edit rule')" :page-heading="__('Edit rule')">
    <x-slot:subtitle>{{ $rule->name }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Rules'), 'url' => route('admin.automation.rules.index')],
            ['label' => $rule->name, 'url' => route('admin.automation.rules.show', $rule)],
            ['label' => __('Edit')],
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

        <form method="POST" action="{{ route('admin.automation.rules.update', $rule) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.automation.rules._form')
            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.automation.rules.show', $rule)" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ __('Save changes') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
