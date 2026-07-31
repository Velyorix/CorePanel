<x-layout.client
    :title="$pageTitle"
    :page-heading="$pageTitle"
>
    <x-slot:subtitle>
        {{ __('Browse official modules and themes. Purchases are completed on CorePanel.org.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            <x-ui.theme-toggle />
            @auth
                <span class="hidden text-body-sm text-muted-foreground sm:inline">
                    {{ auth()->user()->email }}
                </span>
            @endauth
        </div>
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Client'), 'url' => route('client.dashboard')],
            ['label' => __('Marketplace')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('client.marketplace.purchases')" variant="secondary" size="sm">
            {{ __('My purchases') }}
        </x-ui.button>
    </div>

    @if (filled($catalogError))
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Catalogue unavailable')">
                {{ $catalogError }}
            </x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$paginator">
        <x-slot:filters>
            <form method="GET" action="{{ route('client.marketplace.index') }}" class="flex w-full flex-wrap items-end gap-3">
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
                    <x-ui.button :href="route('client.marketplace.index')" variant="ghost" size="sm">
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
                    <a href="{{ route('client.marketplace.show', $item['slug']) }}" class="font-medium text-primary hover:underline">
                        {{ $item['name'] }}
                    </a>
                    <div class="mt-0.5 font-mono text-small text-muted-foreground">{{ $item['slug'] }}</div>
                    @if (filled($item['short_description']))
                        <div class="mt-1 max-w-md text-small text-muted-foreground">{{ $item['short_description'] }}</div>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge variant="neutral">{{ ucfirst($item['product_type']) }}</x-ui.badge>
                </td>
                <td class="px-4 py-3 font-mono text-small">{{ $item['current_version'] ?: '—' }}</td>
                <td class="px-4 py-3 text-small">{{ $item['price_label'] }}</td>
                <td class="px-4 py-3">
                    @if ($item['owned'])
                        <x-ui.badge variant="success">{{ __('Owned') }}</x-ui.badge>
                    @elseif ($item['install_action'] === 'purchase')
                        <x-ui.badge variant="warning">{{ __('Purchase required') }}</x-ui.badge>
                    @elseif ($item['is_free'])
                        <x-ui.badge variant="primary">{{ __('Free') }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">{{ __('Available') }}</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-ui.button :href="route('client.marketplace.show', $item['slug'])" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>

                        @if ($item['install_action'] === 'purchase')
                            <x-ui.button
                                :href="$item['purchase_url']"
                                variant="primary"
                                size="sm"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ __('Buy') }}
                            </x-ui.button>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.client>
