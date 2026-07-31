<x-layout.admin :title="__('Create category')" :page-heading="__('Create category')">
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Categories'), 'url' => route('admin.kb-categories.index')],
            ['label' => __('Create')],
        ]" />
    </x-slot:breadcrumbs>
    @if ($errors->has('category'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('category') }}</x-ui.alert></div>
    @endif
    <x-ui.card :title="__('Category')">
        <form method="POST" action="{{ route('admin.kb-categories.store') }}" class="space-y-6">
            @csrf
            @include('admin.kb-categories._form')
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="primary" size="sm">{{ __('Create category') }}</x-ui.button>
                <x-ui.button :href="route('admin.kb-categories.index')" variant="ghost" size="sm">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
