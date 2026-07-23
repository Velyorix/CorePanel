<x-layout.admin :title="__('Edit category')" :page-heading="__('Edit category')">
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Categories'), 'url' => route('admin.kb-categories.index')],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>
    @if ($errors->has('category'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('category') }}</x-ui.alert></div>
    @endif
    <x-ui.card :title="__('Category')">
        <form method="POST" action="{{ route('admin.kb-categories.update', $category) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.kb-categories._form', ['category' => $category])
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit" variant="primary" size="sm">{{ __('Save changes') }}</x-ui.button>
                <x-ui.button :href="route('admin.kb-categories.index')" variant="ghost" size="sm">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @can('delete', $category)
        <div class="mt-6">
            <form method="POST" action="{{ route('admin.kb-categories.destroy', $category) }}" onsubmit="return confirm(@js(__('Delete this category?')))">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">{{ __('Delete category') }}</x-ui.button>
            </form>
        </div>
    @endcan
</x-layout.admin>
