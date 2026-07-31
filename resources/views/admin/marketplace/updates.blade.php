<x-layout.admin
    :title="__('Marketplace updates')"
    :page-heading="__('Marketplace updates')"
>
    <x-slot:subtitle>
        {{ __('Available updates for packages installed from the marketplace.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Marketplace'), 'url' => route('admin.marketplace.index')],
            ['label' => __('Updates')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('admin.marketplace.index')" variant="secondary" size="sm">
            {{ __('Back to catalogue') }}
        </x-ui.button>

        @if ($canManage)
            <form method="POST" action="{{ route('admin.marketplace.updates.check') }}">
                @csrf
                <x-ui.button type="submit" variant="primary" size="sm">
                    {{ __('Check now') }}
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

    <div class="mb-4 text-body-sm text-muted-foreground">
        @if (filled($checkedAt))
            {{ __('Last check: :at (:count package(s) scanned).', ['at' => $checkedAt, 'count' => $checkedCount]) }}
        @else
            {{ __('No update check has been run yet.') }}
        @endif
    </div>

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Package') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Installed') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Available') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Compatibility') }}</th>
                <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
            </tr>
        </x-slot:head>

        @forelse ($updates as $update)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.marketplace.show', $update->marketplaceSlug) }}" class="font-medium text-primary hover:underline">
                        {{ $update->packageKey }}
                    </a>
                    <div class="mt-0.5 font-mono text-small text-muted-foreground">{{ $update->marketplaceSlug }}</div>
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge variant="neutral">{{ ucfirst($update->productType) }}</x-ui.badge>
                </td>
                <td class="px-4 py-3 font-mono text-small">{{ $update->installedVersion }}</td>
                <td class="px-4 py-3 font-mono text-small">{{ $update->availableVersion }}</td>
                <td class="px-4 py-3">
                    @if ($update->compatible)
                        <x-ui.badge variant="success">{{ __('Compatible') }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="danger">{{ __('Incompatible') }}</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.marketplace.show', $update->marketplaceSlug)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="p-4">
                    <x-ui.empty
                        :title="__('No updates available')"
                        :description="__('Run a check to refresh available marketplace updates.')"
                    />
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-layout.admin>
