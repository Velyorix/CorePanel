<x-layout.client
    :title="__('Marketplace purchases')"
    :page-heading="__('Marketplace purchases')"
>
    <x-slot:subtitle>
        {{ __('Modules and themes covered by the active CorePanel license.') }}
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
            ['label' => __('Purchases')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('client.marketplace.index')" variant="secondary" size="sm">
            {{ __('Browse marketplace') }}
        </x-ui.button>
    </div>

    @unless ($isLicensed)
        <div class="mb-6">
            <x-ui.alert variant="warning" :title="__('License required')">
                {{ __('Activate a valid license to see marketplace purchases linked to this installation.') }}
            </x-ui.alert>
        </div>
    @endunless

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Product') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('SKU') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Granted') }}</th>
                <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
            </tr>
        </x-slot:head>

        @forelse ($purchases as $purchase)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3 font-medium">{{ $purchase['product_name'] }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge variant="neutral">{{ ucfirst($purchase['product_type']) }}</x-ui.badge>
                </td>
                <td class="px-4 py-3 font-mono text-small">{{ $purchase['product_sku'] }}</td>
                <td class="px-4 py-3 text-small text-muted-foreground">
                    {{ $purchase['granted_at'] ?: '—' }}
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button
                        :href="$purchase['purchase_url']"
                        variant="ghost"
                        size="sm"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ __('View on CorePanel.org') }}
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="p-4">
                    <x-ui.empty
                        :title="__('No marketplace purchases yet')"
                        :description="__('Paid modules and themes entitled to this license will appear here.')"
                    >
                        <x-slot:actions>
                            <x-ui.button :href="route('client.marketplace.index')" variant="primary" size="sm">
                                {{ __('Browse marketplace') }}
                            </x-ui.button>
                        </x-slot:actions>
                    </x-ui.empty>
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-layout.client>
