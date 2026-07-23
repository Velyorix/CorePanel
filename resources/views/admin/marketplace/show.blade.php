<x-layout.admin
    :title="$product['name']"
    :page-heading="$product['name']"
>
    <x-slot:subtitle>
        {{ __('Marketplace package details and installation.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Marketplace'), 'url' => route('admin.marketplace.index')],
            ['label' => $product['name']],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('admin.marketplace.index')" variant="secondary" size="sm">
            {{ __('Back to catalogue') }}
        </x-ui.button>

        @if ($canManage && $product['can_install'])
            <form method="POST" action="{{ route('admin.marketplace.install', $product['slug']) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="min-w-40">
                    <x-ui.select name="version" :label="__('Version')">
                        <option value="">{{ __('Latest') }}</option>
                        @foreach ($product['versions'] as $version)
                            <option value="{{ $version['version'] }}">
                                {{ $version['version'] }}
                                @if ($version['is_latest']) ({{ __('latest') }}) @endif
                                @unless ($version['compatible']) — {{ __('incompatible') }} @endunless
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <label class="mb-2 flex items-center gap-2 text-body-sm">
                    <input type="checkbox" name="enable" value="1" class="rounded border-border" checked>
                    {{ __('Enable after install') }}
                </label>
                <x-ui.button type="submit" variant="primary" size="sm">
                    {{ __('Install') }}
                </x-ui.button>
            </form>
        @endif
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

    @if ($product['locally_installed'])
        <div class="mb-6">
            <x-ui.alert variant="success">
                {{ __('Already installed locally as :key.', ['key' => $product['local_package_key']]) }}
            </x-ui.alert>
        </div>
    @elseif (filled($product['entitlement_message']) && ! $product['can_install'])
        <div class="mb-6">
            <x-ui.alert variant="warning" :title="__('Installation unavailable')">
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
                                        @if (filled($version['min_cms_version']) || filled($version['max_cms_version']))
                                            <div class="mt-1 text-small text-muted-foreground">
                                                @if (filled($version['min_cms_version']))
                                                    ≥ {{ $version['min_cms_version'] }}
                                                @endif
                                                @if (filled($version['max_cms_version']))
                                                    ≤ {{ $version['max_cms_version'] }}
                                                @endif
                                            </div>
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
        </x-ui.card>
    </div>
</x-layout.admin>
