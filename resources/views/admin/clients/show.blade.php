@php
    $statusVariant = match ($client->status) {
        \Core\Clients\Enums\ClientStatus::Active => 'success',
        \Core\Clients\Enums\ClientStatus::Suspended => 'warning',
        \Core\Clients\Enums\ClientStatus::Closed => 'danger',
        default => 'neutral',
    };
@endphp

<x-layout.admin
    :title="$client->company_name ?: __('Client')"
    :page-heading="$client->company_name ?: __('Untitled client')"
>
    <x-slot:subtitle>
        {{ __('Client account details and ownership.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Clients'), 'url' => route('admin.clients.index')],
            ['label' => $client->company_name ?: __('Client #'.$client->id)],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('update', $client)
            <x-ui.button :href="route('admin.clients.edit', $client)" variant="secondary" size="sm">
                {{ __('Edit') }}
            </x-ui.button>
        @endcan
        @can('delete', $client)
            <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" onsubmit="return confirm(@js(__('Delete this client?')))">
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

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Client details')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$statusVariant">
                            {{ __(ucfirst($client->status->value)) }}
                        </x-ui.badge>
                    </dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Company name') }}</dt>
                    <dd class="font-medium">{{ $client->company_name ?: '—' }}</dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('VAT number') }}</dt>
                    <dd class="font-medium">{{ $client->vat_number ?: '—' }}</dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Phone') }}</dt>
                    <dd class="font-medium">{{ $client->phone ?: '—' }}</dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Address') }}</dt>
                    <dd class="text-end font-medium">
                        {{ collect([$client->address, $client->postal_code, $client->city, $client->country])->filter()->implode(', ') ?: '—' }}
                    </dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Created') }}</dt>
                    <dd class="font-medium">{{ $client->created_at?->toDayDateTimeString() ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Ownership')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Owner') }}</dt>
                    <dd class="text-end font-medium">
                        @if ($client->owner)
                            <div>{{ $client->owner->name }}</div>
                            <div class="text-small text-muted-foreground">{{ $client->owner->email }}</div>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Members') }}</dt>
                    <dd class="font-medium">{{ $client->memberships->count() }}</dd>
                </div>
            </dl>

            @if ($client->memberships->isNotEmpty())
                <ul class="mt-4 divide-y divide-border rounded-lg border border-border text-body-sm">
                    @foreach ($client->memberships as $membership)
                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                            <div>
                                <div class="font-medium">{{ $membership->user?->name ?: __('Unknown user') }}</div>
                                <div class="text-small text-muted-foreground">{{ $membership->user?->email }}</div>
                            </div>
                            <x-ui.badge variant="neutral">{{ $membership->role }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>
