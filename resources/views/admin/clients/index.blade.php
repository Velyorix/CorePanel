<x-layout.admin
    :title="__('Clients')"
    :page-heading="__('Clients')"
>
    <x-slot:subtitle>
        {{ __('Manage client accounts, ownership, and status.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Clients')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('create', Core\Clients\Models\Client::class)
            <x-ui.button :href="route('admin.clients.create')" variant="primary" size="sm">
                {{ __('Create client') }}
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

    <x-ui.table :paginator="$clients">
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Owner') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Country') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="5" class="p-4">
                    <x-ui.empty
                        :title="__('No clients found')"
                        :description="__('Create a client account to get started.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($clients as $client)
            @php
                $statusVariant = match ($client->status) {
                    \Core\Clients\Enums\ClientStatus::Active => 'success',
                    \Core\Clients\Enums\ClientStatus::Suspended => 'warning',
                    \Core\Clients\Enums\ClientStatus::Closed => 'danger',
                    default => 'neutral',
                };
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $client->company_name ?: __('Untitled client') }}</div>
                    <div class="text-small text-muted-foreground">#{{ $client->id }}</div>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ $client->owner?->email ?? '—' }}
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $client->country ?: '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$statusVariant">
                        {{ $client->status->label() }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.clients.show', $client)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
