@php
    $statusVariant = $category->status === \Core\Products\Enums\ProductCategoryStatus::Active
        ? 'success'
        : 'neutral';
@endphp

<x-layout.admin
    :title="$category->name"
    :page-heading="$category->name"
>
    <x-slot:subtitle>
        {{ __('Product category details.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Categories'), 'url' => route('admin.product-categories.index')],
            ['label' => $category->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('update', $category)
            <x-ui.button :href="route('admin.product-categories.edit', $category)" variant="secondary" size="sm">
                {{ __('Edit') }}
            </x-ui.button>
        @endcan
        @can('delete', $category)
            <form method="POST" action="{{ route('admin.product-categories.destroy', $category) }}" onsubmit="return confirm(@js(__('Delete this category?')))">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">
                    {{ __('Delete') }}
                </x-ui.button>
            </form>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('category'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('category') }}</x-ui.alert>
        </div>
    @endif

    <x-ui.card :title="__('Overview')">
        <dl class="space-y-3 text-body-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                <dd>
                    <x-ui.badge :variant="$statusVariant">{{ $category->status->label() }}</x-ui.badge>
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">{{ __('Slug') }}</dt>
                <dd class="font-mono text-small">{{ $category->slug }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">{{ __('Parent') }}</dt>
                <dd>
                    @if ($category->parent)
                        <a href="{{ route('admin.product-categories.show', $category->parent) }}" class="text-primary-700 hover:underline dark:text-primary-300">
                            {{ $category->parent->name }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">{{ __('Products') }}</dt>
                <dd>{{ $category->products_count }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">{{ __('Sort order') }}</dt>
                <dd>{{ $category->sort_order }}</dd>
            </div>
            @if ($category->description)
                <div>
                    <dt class="mb-1 text-muted-foreground">{{ __('Description') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $category->description }}</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>
</x-layout.admin>
