<x-layout.admin
    :title="__('Products')"
    :page-heading="__('Products')"
>
    <x-slot:subtitle>
        {{ __('Manage the product catalog, pricing, and lifecycle.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Products')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.product-categories.index')" variant="secondary" size="sm">
            {{ __('Categories') }}
        </x-ui.button>
        @can('create', Core\Products\Models\Product::class)
            <x-ui.button :href="route('admin.products.create')" variant="primary" size="sm">
                {{ __('Create product') }}
            </x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$products">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.products.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Name, slug, module, ID…')"
                    />
                </div>

                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-40">
                    <x-ui.select name="type" :label="__('Type')">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type']?->value ?? null) === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-44">
                    <x-ui.select name="category_id" :label="__('Category')">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                @if (filled(request('sort')))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif
                @if (filled(request('dir')))
                    <input type="hidden" name="dir" value="{{ request('dir') }}">
                @endif

                <x-ui.button type="submit" variant="secondary" size="sm">
                    {{ __('Apply') }}
                </x-ui.button>

                @if (filled($filters['q']) || $filters['status'] !== null || $filters['type'] !== null || $filters['category_id'] !== null)
                    <x-ui.button :href="route('admin.products.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="name">{{ __('Name') }}</x-ui.table-heading>
                <x-ui.table-heading sort="type">{{ __('Type') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Category') }}</th>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="5" class="p-4">
                    <x-ui.empty
                        :title="__('No products found')"
                        :description="filled($filters['q']) || $filters['status'] !== null || $filters['type'] !== null || $filters['category_id'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Create a product to get started.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($products as $product)
            @php
                $statusVariant = match ($product->status) {
                    \Core\Products\Enums\ProductStatus::Published => 'success',
                    \Core\Products\Enums\ProductStatus::Draft => 'neutral',
                    \Core\Products\Enums\ProductStatus::Archived => 'warning',
                    default => 'neutral',
                };
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $product->name }}</div>
                    <div class="text-small text-muted-foreground">{{ $product->slug }}</div>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $product->type->label() }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ $product->category?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$statusVariant">
                        {{ $product->status->label() }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.products.show', $product)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
