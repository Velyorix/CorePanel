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
        ServiceAction::Start->value => 'client.services.start',
        ServiceAction::Stop->value => 'client.services.stop',
        ServiceAction::Restart->value => 'client.services.restart',
        ServiceAction::Reinstall->value => 'client.services.reinstall',
    ];

    $dangerActions = [
        ServiceAction::Reinstall->value,
    ];

    $confirmActions = [
        ServiceAction::Reinstall->value,
    ];
@endphp

<x-layout.client
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Service details, access links, and control actions.') }}
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
            ['label' => __('Services'), 'url' => route('client.services.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
        <x-ui.button :href="route('client.services.index')" variant="secondary" size="sm">
            {{ __('Back to services') }}
        </x-ui.button>

        @if ($canManage)
            <div class="flex flex-wrap items-center gap-2">
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
            </div>
        @endif
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
                @if ($service->suspended_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Suspended') }}</dt>
                        <dd>{{ $service->suspended_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Product & order')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Product') }}</dt>
                    <dd>{{ $service->product?->name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Order') }}</dt>
                    <dd>
                        @if ($service->order)
                            <a href="{{ route('client.orders.show', $service->order) }}" class="text-primary hover:underline">
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
    </div>

    <div class="mt-6">
        <x-ui.card :title="__('Action log')">
            @if ($actionLogs->isEmpty())
                <x-ui.empty
                    :title="__('No actions logged yet')"
                    :description="__('Control actions will appear here.')"
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
</x-layout.client>
