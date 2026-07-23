<x-layout.admin
    :title="__('Marketplace')"
    :page-heading="__('Marketplace')"
>
    <x-slot:subtitle>
        {{ __('Browse and install modules and themes from the official catalogue.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Marketplace')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.marketplace.updates')" variant="secondary" size="sm">
            {{ __('Updates') }}
            @if ($updatesCount > 0)
                <span class="ms-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary-600 px-1.5 text-xs text-white">
                    {{ $updatesCount }}
                </span>
            @endif
        </x-ui.button>
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

    @if (filled($catalogError))
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Catalogue unavailable')">
                {{ $catalogError }}
            </x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$paginator">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.marketplace.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Name, SKU, slug…')"
                    />
                </div>

                <div class="min-w-36">
                    <x-ui.select name="product_type" :label="__('Type')">
                        <option value="">{{ __('All types') }}</option>
                        <option value="module" @selected($filters['product_type'] === 'module')>{{ __('Module') }}</option>
                        <option value="theme" @selected($filters['product_type'] === 'theme')>{{ __('Theme') }}</option>
                    </x-ui.select>
                </div>

                <div class="min-w-36">
                    <x-ui.select name="price" :label="__('Price')">
                        <option value="">{{ __('All prices') }}</option>
                        <option value="free" @selected($filters['price'] === 'free')>{{ __('Free') }}</option>
                        <option value="paid" @selected($filters['price'] === 'paid')>{{ __('Paid') }}</option>
                    </x-ui.select>
                </div>

                <div class="min-w-40">
                    <x-ui.select name="sort" :label="__('Sort')">
                        <option value="">{{ __('Default') }}</option>
                        <option value="downloads" @selected($filters['sort'] === 'downloads')>{{ __('Downloads') }}</option>
                        <option value="rating" @selected($filters['sort'] === 'rating')>{{ __('Rating') }}</option>
                        <option value="price_asc" @selected($filters['sort'] === 'price_asc')>{{ __('Price ↑') }}</option>
                        <option value="price_desc" @selected($filters['sort'] === 'price_desc')>{{ __('Price ↓') }}</option>
                    </x-ui.select>
                </div>

                <div class="min-w-40">
                    <x-ui.input
                        name="category"
                        :label="__('Category')"
                        :value="$filters['category']"
                        :placeholder="__('billing, design…')"
                    />
                </div>

                <x-ui.button type="submit" variant="secondary" size="sm">
                    {{ __('Apply') }}
                </x-ui.button>

                @if (filled($filters['q']) || filled($filters['category']) || filled($filters['price']) || filled($filters['product_type']) || filled($filters['sort']))
                    <x-ui.button :href="route('admin.marketplace.index')" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Product') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Version') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Price') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="6" class="p-4">
                    <x-ui.empty
                        :title="__('No marketplace products found')"
                        :description="filled($catalogError)
                            ? __('The catalogue could not be loaded.')
                            : __('Try adjusting your search or filters.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($products as $item)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.marketplace.show', $item['slug']) }}" class="font-medium text-primary hover:underline">
                        {{ $item['name'] }}
                    </a>
                    <div class="mt-0.5 font-mono text-small text-muted-foreground">{{ $item['slug'] }}</div>
                    @if (filled($item['short_description']))
                        <div class="mt-1 max-w-md text-small text-muted-foreground">{{ $item['short_description'] }}</div>
                    @endif
                    @if (filled($item['developer']) || filled($item['category']))
                        <div class="mt-1 text-small text-muted-foreground">
                            @if (filled($item['developer']))
                                {{ $item['developer'] }}
                            @endif
                            @if (filled($item['developer']) && filled($item['category']))
                                ·
                            @endif
                            @if (filled($item['category']))
                                {{ $item['category'] }}
                            @endif
                        </div>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge variant="neutral">{{ ucfirst($item['product_type']) }}</x-ui.badge>
                    @if ($item['is_vip'])
                        <x-ui.badge variant="primary">VIP</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3 font-mono text-small">{{ $item['current_version'] ?: '—' }}</td>
                <td class="px-4 py-3 text-small">{{ $item['price_label'] }}</td>
                <td class="px-4 py-3">
                    @if ($item['locally_installed'])
                        <x-ui.badge variant="success">{{ __('Installed') }}</x-ui.badge>
                    @elseif ($item['install_action'] === 'purchase')
                        <x-ui.badge variant="warning">{{ __('License required') }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">{{ __('Available') }}</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-ui.button :href="route('admin.marketplace.show', $item['slug'])" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>

                        @if ($canManage && $item['can_install'])
                            <form method="POST" action="{{ route('admin.marketplace.install', $item['slug']) }}">
                                @csrf
                                <input type="hidden" name="enable" value="1">
                                <x-ui.button type="submit" variant="primary" size="sm">
                                    {{ __('Install') }}
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
