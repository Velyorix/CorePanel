<x-layout.admin
    :title="__('Services')"
    :page-heading="__('Services')"
>
    <x-slot:subtitle>
        {{ __('Manage provisioned client services and control actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Services')],
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

    <x-ui.table :paginator="$services">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.services.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Hostname, IP, client, product…')"
                    />
                </div>

                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
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
                    <x-ui.button :href="route('admin.services.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="id">{{ __('Service') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Client') }}</th>
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
                <td colspan="8" class="p-4">
                    <x-ui.empty
                        :title="__('No services found')"
                        :description="filled($filters['q']) || $filters['status'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Services will appear here after orders are paid.')"
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
                    @if ($service->client)
                        <a href="{{ route('admin.clients.show', $service->client) }}" class="text-primary hover:underline">
                            {{ $service->client->company_name ?: __('Client #'.$service->client->id) }}
                        </a>
                    @else
                        —
                    @endif
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
                    <x-ui.button :href="route('admin.services.show', $service)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
