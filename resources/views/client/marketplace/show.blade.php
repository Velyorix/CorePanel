<x-layout.client
    :title="$product['name']"
    :page-heading="$product['name']"
>
    <x-slot:subtitle>
        {{ __('Marketplace package details. Purchases are completed on CorePanel.org.') }}
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
            ['label' => __('Marketplace'), 'url' => route('client.marketplace.index')],
            ['label' => $product['name']],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('client.marketplace.index')" variant="secondary" size="sm">
            {{ __('Back to catalogue') }}
        </x-ui.button>

        <div class="flex flex-wrap items-center gap-2">
            @if ($product['owned'])
                <x-ui.badge variant="success">{{ __('Owned on this license') }}</x-ui.badge>
            @elseif ($product['can_purchase'])
                <x-ui.button
                    :href="$product['purchase_url']"
                    variant="primary"
                    size="sm"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ __('Buy on CorePanel.org') }}
                </x-ui.button>
            @elseif ($product['is_free'])
                <x-ui.badge variant="primary">{{ __('Free') }}</x-ui.badge>
            @endif
        </div>
    </div>

    @if (filled($product['entitlement_message']) && $product['can_purchase'])
        <div class="mb-6">
            <x-ui.alert variant="warning" :title="__('Purchase required')">
                {{ $product['entitlement_message'] }}
            </x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Slug') }}</dt>
                    <dd class="font-mono text-small">{{ $product['slug'] }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('SKU') }}</dt>
                    <dd class="font-mono text-small">{{ $product['sku'] ?: '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Type') }}</dt>
                    <dd>
                        <x-ui.badge variant="neutral">{{ ucfirst($product['product_type']) }}</x-ui.badge>
                        @if ($product['is_vip'])
                            <x-ui.badge variant="primary">VIP</x-ui.badge>
                        @endif
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Price') }}</dt>
                    <dd>{{ $product['price_label'] }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Current version') }}</dt>
                    <dd class="font-mono text-small">{{ $product['current_version'] ?: '—' }}</dd>
                </div>
                @if (filled($product['developer']))
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Developer') }}</dt>
                        <dd>{{ $product['developer'] }}</dd>
                    </div>
                @endif
                @if (filled($product['category']))
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Category') }}</dt>
                        <dd>{{ $product['category'] }}</dd>
                    </div>
                @endif
                @if (filled($product['short_description']))
                    <div>
                        <dt class="mb-1 text-muted-foreground">{{ __('Description') }}</dt>
                        <dd>{{ $product['short_description'] }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Published versions')">
            @if ($product['versions'] === [])
                <p class="text-body-sm text-muted-foreground">{{ __('No published versions available.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-muted-foreground">
                                <th class="px-2 py-2 font-medium">{{ __('Version') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('Compatibility') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('Archive') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($product['versions'] as $version)
                                <tr class="border-b border-border/60">
                                    <td class="px-2 py-2 font-mono">
                                        {{ $version['version'] }}
                                        @if ($version['is_latest'])
                                            <x-ui.badge variant="primary">{{ __('Latest') }}</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2">
                                        @if ($version['compatible'])
                                            <x-ui.badge variant="success">{{ __('Compatible') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="danger">{{ __('Incompatible') }}</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2">
                                        {{ $version['has_archive'] ? __('Yes') : __('No') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <p class="mt-4 text-small text-muted-foreground">
                {{ __('Package installation is managed by your hosting administrator.') }}
            </p>
        </x-ui.card>
    </div>
</x-layout.client>
