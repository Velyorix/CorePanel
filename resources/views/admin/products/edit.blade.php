<x-layout.admin
    :title="__('Edit product')"
    :page-heading="__('Edit product')"
>
    <x-slot:subtitle>
        {{ $product->name }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Products'), 'url' => route('admin.products.index')],
            ['label' => $product->name, 'url' => route('admin.products.show', $product)],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.card :title="__('Product details')">
        @if ($errors->any())
            <div class="mb-4">
                <x-ui.alert variant="danger" :title="__('Unable to update product')">
                    <ul class="list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('admin.products._form')

            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-ui.button :href="route('admin.products.show', $product)" variant="secondary">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary">
                    {{ __('Save changes') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
