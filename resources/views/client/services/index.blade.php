@php
    $clientStatuses = $statuses;
@endphp

<x-layout.client
    :title="__('Services')"
    :page-heading="__('Your services')"
>
    <x-slot:subtitle>
        {{ __('Manage your provisioned services and access control panels.') }}
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
            ['label' => __('Services')],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($clientMissing)
        <x-ui.empty
            :title="__('No client account yet')"
            :description="__('Services will appear here once your account is linked to a client.')"
        />
    @else
        <x-ui.table :paginator="$services">
            <x-slot:filters>
                <form method="GET" action="{{ route('client.services.index') }}" class="flex w-full flex-wrap items-end gap-3">
                    <div class="min-w-56 flex-1">
                        <x-ui.input
                            name="q"
                            :label="__('Search')"
                            :value="$filters['q']"
                            :placeholder="__('Hostname, IP, product…')"
                        />
                    </div>

                    <div class="min-w-40">
                        <x-ui.select name="status" :label="__('Status')">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($clientStatuses as $status)
                                <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    @if (filled(request('sort')))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if (filled(request('dir')))
                        <input type="hidden" name="dir" value="{{ request('dir') }}">
                    @endif

                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Apply') }}
                    </x-ui.button>

                    @if (filled($filters['q']) || $filters['status'] !== null)
                        <x-ui.button :href="route('client.services.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                            {{ __('Clear') }}
                        </x-ui.button>
                    @endif
                </form>
            </x-slot:filters>

            <x-slot:head>
                <tr>
                    <x-ui.table-heading sort="hostname">{{ __('Service') }}</x-ui.table-heading>
                    <th class="px-4 py-3 font-medium">{{ __('Product') }}</th>
                    <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="module">{{ __('Module') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="next_billing_date">{{ __('Next billing') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="created_at">{{ __('Created') }}</x-ui.table-heading>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </x-slot:head>

            <x-slot:empty>
                <tr>
                    <td colspan="7" class="p-4">
                        <x-ui.empty
                            :title="__('No services yet')"
                            :description="filled($filters['q']) || $filters['status'] !== null
                                ? __('Try adjusting your search or filters.')
                                : __('Services will appear here after your orders are provisioned.')"
                        />
                    </td>
                </tr>
            </x-slot:empty>

            @foreach ($services as $service)
                @php
                    $statusVariant = match ($service->status) {
                        \Core\Services\Enums\ServiceStatus::Active => 'success',
                        \Core\Services\Enums\ServiceStatus::Suspended => 'warning',
                        \Core\Services\Enums\ServiceStatus::Failed => 'danger',
                        \Core\Services\Enums\ServiceStatus::Provisioning => 'primary',
                        \Core\Services\Enums\ServiceStatus::Terminated,
                        \Core\Services\Enums\ServiceStatus::Cancelled => 'neutral',
                        default => 'neutral',
                    };
                @endphp
                <tr class="hover:bg-muted/40">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $service->hostname ?: __('Service #'.$service->id) }}</div>
                        <div class="text-small text-muted-foreground">
                            #{{ $service->id }}
                            @if ($service->ip_address)
                                · {{ $service->ip_address }}
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        {{ $service->product?->name ?: '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$statusVariant">{{ $service->status->label() }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-body-sm">{{ $service->module ?: '—' }}</td>
                    <td class="px-4 py-3 text-body-sm">
                        {{ $service->next_billing_date?->timezone(config('app.timezone'))->format('Y-m-d') ?: '—' }}
                    </td>
                    <td class="px-4 py-3 text-body-sm">
                        {{ $service->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </td>
                    <td class="px-4 py-3 text-end">
                        <x-ui.button :href="route('client.services.show', $service)" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.client>
