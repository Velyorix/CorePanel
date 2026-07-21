@php
    use Core\Services\Enums\ServiceAction;
    use Core\Services\Enums\ServiceStatus;

    $statusVariant = match ($service->status) {
        ServiceStatus::Active => 'success',
        ServiceStatus::Suspended => 'warning',
        ServiceStatus::Failed => 'danger',
        ServiceStatus::Provisioning => 'primary',
        ServiceStatus::Terminated, ServiceStatus::Cancelled => 'neutral',
        default => 'neutral',
    };

    $heading = $service->hostname ?: __('Service #'.$service->id);

    $actionRoutes = [
        ServiceAction::Start->value => 'admin.services.start',
        ServiceAction::Stop->value => 'admin.services.stop',
        ServiceAction::Restart->value => 'admin.services.restart',
        ServiceAction::Suspend->value => 'admin.services.suspend',
        ServiceAction::Unsuspend->value => 'admin.services.unsuspend',
        ServiceAction::Terminate->value => 'admin.services.terminate',
        ServiceAction::Reinstall->value => 'admin.services.reinstall',
    ];

    $dangerActions = [
        ServiceAction::Suspend->value,
        ServiceAction::Terminate->value,
        ServiceAction::Reinstall->value,
    ];

    $confirmActions = [
        ServiceAction::Terminate->value,
        ServiceAction::Reinstall->value,
    ];
@endphp

<x-layout.admin
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Service details, access links, and control actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Services'), 'url' => route('admin.services.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('manage', $service)
            @if ($canSync ?? false)
                <form method="POST" action="{{ route('admin.services.sync', $service) }}" class="inline">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm">
                        {{ __('Sync with provider') }}
                    </x-ui.button>
                </form>
            @endif

            @foreach ($allowedActions as $action)
                @continue(! isset($actionRoutes[$action->value]))
                <form
                    method="POST"
                    action="{{ route($actionRoutes[$action->value], $service) }}"
                    @if (in_array($action->value, $confirmActions, true))
                        onsubmit="return confirm(@js(__('Are you sure you want to :action this service?', ['action' => $action->label()])))"
                    @endif
                >
                    @csrf
                    <x-ui.button
                        type="submit"
                        :variant="in_array($action->value, $dangerActions, true) ? 'danger' : 'secondary'"
                        size="sm"
                    >
                        {{ $action->label() }}
                    </x-ui.button>
                </form>
            @endforeach
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('action'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('action') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$statusVariant">{{ $service->status->label() }}</x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Module') }}</dt>
                    <dd>{{ $service->module ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('External ID') }}</dt>
                    <dd class="font-mono text-small">{{ $service->external_id ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Hostname') }}</dt>
                    <dd>{{ $service->hostname ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('IP address') }}</dt>
                    <dd class="font-mono text-small">{{ $service->ip_address ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Billing cycle') }}</dt>
                    <dd>{{ $service->billing_cycle?->label() ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Next billing') }}</dt>
                    <dd>{{ $service->next_billing_date?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Started') }}</dt>
                    <dd>{{ $service->started_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Suspended') }}</dt>
                    <dd>{{ $service->suspended_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Terminated') }}</dt>
                    <dd>{{ $service->terminated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Client & product')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Client') }}</dt>
                    <dd>
                        @if ($service->client)
                            <a href="{{ route('admin.clients.show', $service->client) }}" class="text-primary hover:underline">
                                {{ $service->client->company_name ?: __('Client #'.$service->client->id) }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Owner') }}</dt>
                    <dd>{{ $service->client?->owner?->email ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Product') }}</dt>
                    <dd>
                        @if ($service->product)
                            <a href="{{ route('admin.products.show', $service->product) }}" class="text-primary hover:underline">
                                {{ $service->product->name }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Order') }}</dt>
                    <dd>
                        @if ($service->order)
                            <a href="{{ route('admin.orders.show', $service->order) }}" class="text-primary hover:underline">
                                {{ $service->order->order_number ?: '#'.$service->order->id }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Technical access')">
            <div class="flex flex-wrap gap-2">
                @if ($access->canOpenPanel)
                    <x-ui.button
                        :href="$access->panelUrl"
                        variant="primary"
                        size="sm"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ __('Open panel') }}
                    </x-ui.button>
                @else
                    <span class="text-body-sm text-muted-foreground">{{ __('Panel unavailable') }}</span>
                @endif

                @if ($access->canOpenConsole)
                    <x-ui.button
                        :href="$access->consoleUrl"
                        variant="secondary"
                        size="sm"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ __('Open console') }}
                    </x-ui.button>
                @else
                    <span class="text-body-sm text-muted-foreground">{{ __('Console unavailable') }}</span>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Configuration')">
            @if (filled($service->config_data))
                <pre class="overflow-x-auto rounded-md bg-muted/40 p-3 text-small">{{ json_encode($service->config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="text-body-sm text-muted-foreground">{{ __('No commercial configuration snapshot.') }}</p>
            @endif
            <p class="mt-3 text-small text-muted-foreground">
                {{ __('Encrypted credentials are not shown here.') }}
            </p>
        </x-ui.card>
    </div>

    <div class="mt-6">
        <x-ui.card :title="__('Action log')">
            @if ($actionLogs->isEmpty())
                <x-ui.empty
                    :title="__('No actions logged yet')"
                    :description="__('Control and plan-change actions will appear here.')"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-body-sm">
                        <thead>
                            <tr class="border-b border-border text-muted-foreground">
                                <th class="px-2 py-2 font-medium">{{ __('Action') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('Status') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('Performed by') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('When') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actionLogs as $log)
                                <tr class="border-b border-border/60">
                                    <td class="px-2 py-2">{{ $log->action->label() }}</td>
                                    <td class="px-2 py-2">{{ $log->status->value }}</td>
                                    <td class="px-2 py-2">{{ $log->performer?->name ?: __('System') }}</td>
                                    <td class="px-2 py-2">
                                        {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?: '—' }}
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
        <x-ui.card :title="__('Sync history')">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p class="text-body-sm text-muted-foreground">
                    {{ __('Recent provider sync runs for this service.') }}
                </p>
                <x-ui.button :href="route('admin.sync-logs.index', ['q' => $service->id])" variant="ghost" size="sm">
                    {{ __('View all sync logs') }}
                </x-ui.button>
            </div>

            @if ($syncLogs->isEmpty())
                <x-ui.empty
                    :title="__('No sync logs yet')"
                    :description="__('Run a manual sync or wait for the scheduled job.')"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-body-sm">
                        <thead>
                            <tr class="border-b border-border text-muted-foreground">
                                <th class="px-2 py-2 font-medium">{{ __('When') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('Outcome') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('Message') }}</th>
                                <th class="px-2 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($syncLogs as $syncLog)
                                <tr class="border-b border-border/60">
                                    <td class="px-2 py-2">
                                        {{ $syncLog->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?: '—' }}
                                    </td>
                                    <td class="px-2 py-2">
                                        <x-ui.badge :variant="$syncLog->outcome->badgeVariant()">
                                            {{ $syncLog->outcome->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="max-w-md truncate px-2 py-2 text-muted-foreground" title="{{ $syncLog->message }}">
                                        {{ $syncLog->message ?: '—' }}
                                    </td>
                                    <td class="px-2 py-2 text-end">
                                        <x-ui.button :href="route('admin.sync-logs.show', $syncLog)" variant="ghost" size="sm">
                                            {{ __('Details') }}
                                        </x-ui.button>
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
