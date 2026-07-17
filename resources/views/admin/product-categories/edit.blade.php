<x-layout.admin
    :title="__('Edit category')"
    :page-heading="__('Edit category')"
>
    <x-slot:subtitle>
        {{ $category->name }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Categories'), 'url' => route('admin.product-categories.index')],
            ['label' => $category->name, 'url' => route('admin.product-categories.show', $category)],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Category details')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to update category')">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.product-categories.update', $category) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('admin.product-categories._form')

            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.product-categories.show', $category)" variant="secondary">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    {{ __('Save changes') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
