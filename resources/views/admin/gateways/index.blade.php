<x-layout.admin
    :title="__('Payment gateways')"
    :page-heading="__('Payment gateways')"
>
    <x-slot:subtitle>
        {{ __('Enable, configure, and order payment gateways available at checkout.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
            ['label' => __('Payment gateways')],
        ]" />
    </x-slot:breadcrumbs>

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

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Order') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Gateway') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
            </tr>
        </x-slot:head>

        @forelse ($gateways as $index => $gateway)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-small text-muted-foreground">{{ $gateway['sort_order'] }}</span>
                        @if ($canManage)
                            <div class="flex flex-col gap-1">
                                <form method="POST" action="{{ route('admin.gateways.move-up', $gateway['key']) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="ghost" size="sm" :disabled="$index === 0">
                                        ↑
                                    </x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.gateways.move-down', $gateway['key']) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="ghost" size="sm" :disabled="$index === $gateways->count() - 1">
                                        ↓
                                    </x-ui.button>
                                </form>
                            </div>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.gateways.show', $gateway['key']) }}" class="font-medium text-primary hover:underline">
                        {{ $gateway['label'] }}
                    </a>
                    <div class="mt-0.5 font-mono text-small text-muted-foreground">{{ $gateway['key'] }}</div>
                </td>
                <td class="px-4 py-3">
                    @if ($gateway['enabled'])
                        <x-ui.badge variant="success">{{ __('Enabled') }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">{{ __('Disabled') }}</x-ui.badge>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-ui.button :href="route('admin.gateways.show', $gateway['key'])" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>

                        @if ($canManage)
                            @if ($gateway['enabled'])
                                <form method="POST" action="{{ route('admin.gateways.disable', $gateway['key']) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        {{ __('Disable') }}
                                    </x-ui.button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.gateways.enable', $gateway['key']) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="primary" size="sm">
                                        {{ __('Enable') }}
                                    </x-ui.button>
                                </form>
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                    {{ __('No payment gateways are registered.') }}
                </td>
            </tr>
        @endforelse
    </x-ui.table>
</x-layout.admin>
