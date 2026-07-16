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
        @can('impersonate', $client)
            @if ($client->owner)
                <form method="POST" action="{{ route('admin.clients.impersonate', $client) }}" onsubmit="return confirm(@js(__('Impersonate this client owner?')))">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Impersonate') }}
                    </x-ui.button>
                </form>
            @endif
        @endcan
        @can('update', $client)
            @if ($client->status === \Core\Clients\Enums\ClientStatus::Active)
                <form method="POST" action="{{ route('admin.clients.suspend', $client) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Suspend') }}
                    </x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.clients.close', $client) }}" onsubmit="return confirm(@js(__('Close this client account?')))">
                    @csrf
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Close') }}
                    </x-ui.button>
                </form>
            @elseif ($client->status === \Core\Clients\Enums\ClientStatus::Suspended)
                <form method="POST" action="{{ route('admin.clients.unsuspend', $client) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Unsuspend') }}
                    </x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.clients.close', $client) }}" onsubmit="return confirm(@js(__('Close this client account?')))">
                    @csrf
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Close') }}
                    </x-ui.button>
                </form>
            @elseif ($client->status === \Core\Clients\Enums\ClientStatus::Closed)
                <form method="POST" action="{{ route('admin.clients.reopen', $client) }}" onsubmit="return confirm(@js(__('Reopen this client account?')))">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Reopen') }}
                    </x-ui.button>
                </form>
            @endif

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

    @if ($errors->has('membership'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('membership') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('status'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('impersonation'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('impersonation') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('note'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('note') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Client details')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$statusVariant">
                            {{ $client->status->label() }}
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
                    <dt class="text-muted-foreground">{{ __('Primary owner') }}</dt>
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
        </x-ui.card>
    </div>

    <div class="mt-6">
        @include('admin.clients._members')
    </div>

    <div class="mt-6">
        @if ($errors->has('invitation'))
            <div class="mb-4">
                <x-ui.alert variant="danger">{{ $errors->first('invitation') }}</x-ui.alert>
            </div>
        @endif

        <x-ui.card :title="__('Invitations')">
            @can('update', $client)
                <form method="POST" action="{{ route('admin.clients.invitations.store', $client) }}" class="mb-6 space-y-4">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input
                            name="email"
                            :label="__('Email')"
                            type="email"
                            required
                            autocomplete="email"
                        />

                        <x-ui.select name="role" :label="__('Role')" required>
                            @foreach ($membershipRoles as $role)
                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="primary">
                            {{ __('Send invitation') }}
                        </x-ui.button>
                    </div>
                </form>
            @endcan

            @if ($pendingInvitations->isEmpty())
                <x-ui.empty
                    :title="__('No pending invitations')"
                    :description="__('Invite users by email to attach them to this client account.')"
                />
            @else
                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="min-w-full divide-y divide-border text-start text-body-sm">
                        <thead class="bg-muted/60 text-small font-medium uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">{{ __('Email') }}</th>
                                <th class="px-4 py-3">{{ __('Role') }}</th>
                                <th class="px-4 py-3">{{ __('Expires at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($pendingInvitations as $invitation)
                                <tr class="hover:bg-muted/40">
                                    <td class="px-4 py-3 font-medium">{{ $invitation->email }}</td>
                                    <td class="px-4 py-3">
                                        <x-ui.badge variant="neutral">{{ $invitation->role->label() }}</x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ $invitation->expires_at?->toDayDateTimeString() ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="mt-6">
        @include('admin.clients._notes')
    </div>
</x-layout.admin>
