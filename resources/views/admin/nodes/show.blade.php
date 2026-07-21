@php
    use Core\Nodes\Enums\NodeCredentialField;

    /** @var \Core\Nodes\Models\Node $node */
    $credentialValues = is_array($node->credentials) ? $node->credentials : [];
    $capacityUsage = $node->capacityUsage();
    $healthSnapshot = $node->healthSnapshot();
@endphp

<x-layout.admin
    :title="$node->name"
    :page-heading="$node->name"
>
    <x-slot:subtitle>
        {{ __('Node details and configuration.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Infrastructure'), 'url' => route('admin.nodes.index')],
            ['label' => __('Nodes'), 'url' => route('admin.nodes.index')],
            ['label' => $node->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <x-ui.button :href="route('admin.nodes.index')" variant="secondary" size="sm">
            {{ __('Back to list') }}
        </x-ui.button>

        <div class="flex flex-wrap items-center gap-2">
            @can('update', $node)
                @if (filled($node->module))
                    <form method="POST" action="{{ route('admin.nodes.sync', $node) }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm">
                            {{ __('Sync') }}
                        </x-ui.button>
                    </form>

                    <form method="POST" action="{{ route('admin.nodes.test-connection.node', $node) }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm">
                            {{ __('Test connection') }}
                        </x-ui.button>
                    </form>
                @endif

                @if ($node->status === \Core\Nodes\Enums\NodeStatus::Maintenance)
                    <form method="POST" action="{{ route('admin.nodes.enable', $node) }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Exit maintenance') }}
                        </x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.nodes.maintenance', $node) }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Set maintenance') }}
                        </x-ui.button>
                    </form>
                @endif

                @if ($node->status !== \Core\Nodes\Enums\NodeStatus::Disabled)
                    <form method="POST" action="{{ route('admin.nodes.disable', $node) }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm">
                            {{ __('Disable') }}
                        </x-ui.button>
                    </form>
                @endif

                @if ($node->status !== \Core\Nodes\Enums\NodeStatus::Active)
                    <form method="POST" action="{{ route('admin.nodes.enable', $node) }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm">
                            {{ __('Enable') }}
                        </x-ui.button>
                    </form>
                @endif
            @endcan

            @can('delete', $node)
                <form method="POST" action="{{ route('admin.nodes.destroy', $node) }}" class="inline" onsubmit="return confirm(@js(__('Delete this node?')))">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Delete') }}
                    </x-ui.button>
                </form>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('node'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('node') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        @php
                            $statusVariant = match ($node->status) {
                                \Core\Nodes\Enums\NodeStatus::Active => 'success',
                                \Core\Nodes\Enums\NodeStatus::Maintenance => 'warning',
                                \Core\Nodes\Enums\NodeStatus::Offline => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <x-ui.badge :variant="$statusVariant">{{ $node->status->label() }}</x-ui.badge>
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Health') }}</dt>
                    <dd>
                        @php
                            $healthVariant = match ($healthSnapshot->state) {
                                \Core\Nodes\Enums\NodeHealthState::Online => 'success',
                                \Core\Nodes\Enums\NodeHealthState::Degraded => 'warning',
                                \Core\Nodes\Enums\NodeHealthState::Offline => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <x-ui.badge :variant="$healthVariant">{{ $healthSnapshot->state->label() }}</x-ui.badge>
                        @if ($healthSnapshot->checkedAt)
                            <div class="mt-2 text-small text-muted-foreground">
                                {{ __('Last checked') }}: {{ $healthSnapshot->checkedAt }}
                                @if ($healthSnapshot->latencyMs !== null)
                                    · {{ $healthSnapshot->latencyMs }} ms
                                @endif
                            </div>
                        @endif
                        @if ($healthSnapshot->message)
                            <div class="mt-1 text-small text-muted-foreground">{{ $healthSnapshot->message }}</div>
                        @endif
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Hostname') }}</dt>
                    <dd class="font-mono text-small">{{ $node->hostname }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Module') }}</dt>
                    <dd>{{ $node->module ?? '—' }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Type') }}</dt>
                    <dd>{{ $node->type->label() }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Group') }}</dt>
                    <dd>{{ $node->group?->name ?? '—' }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Capacity') }}</dt>
                    <dd>
                        <span>
                            {{ $node->allocated_services_count }}
                            @if ($node->max_services !== null)
                                / {{ $node->max_services }}
                            @endif
                            {{ __('services') }}
                        </span>
                        @if ($node->max_cpu_cores !== null || $node->max_ram_mb !== null || $node->max_disk_gb !== null || $node->max_bandwidth_mbps !== null)
                            <div class="mt-2 text-small text-muted-foreground">
                                @if ($node->max_cpu_cores !== null)
                                    {{ $capacityUsage->cpuCores }}/{{ $node->max_cpu_cores }} {{ __('CPU') }}
                                @endif
                                @if ($node->max_ram_mb !== null)
                                    @if ($node->max_cpu_cores !== null) · @endif
                                    {{ number_format($capacityUsage->ramMb) }}/{{ number_format($node->max_ram_mb) }} {{ __('MB RAM') }}
                                @endif
                                @if ($node->max_disk_gb !== null)
                                    @if ($node->max_cpu_cores !== null || $node->max_ram_mb !== null) · @endif
                                    {{ $capacityUsage->diskGb }}/{{ $node->max_disk_gb }} {{ __('GB disk') }}
                                @endif
                                @if ($node->max_bandwidth_mbps !== null)
                                    @if ($node->max_cpu_cores !== null || $node->max_ram_mb !== null || $node->max_disk_gb !== null) · @endif
                                    {{ number_format($capacityUsage->peakBandwidthMbps(), 1) }}/{{ $node->max_bandwidth_mbps }} {{ __('Mbps') }}
                                @endif
                            </div>
                        @endif
                        @if ($capacityUsage->syncedAt)
                            <div class="mt-2 text-small text-muted-foreground">
                                {{ __('Last synced') }}: {{ $capacityUsage->syncedAt }}
                                @if ($capacityUsage->source)
                                    ({{ $capacityUsage->source }})
                                @endif
                            </div>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Configuration')">
            @if (! empty($node->config))
                <pre class="max-h-96 overflow-auto rounded-md border border-border bg-surface p-4 text-small text-foreground">{{ json_encode($node->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <x-ui.empty :title="__('No configuration')" :description="__('This node has no config data.')"/>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Credentials')">
            <p class="text-small text-muted-foreground">
                {{ __('Configured credentials are encrypted at rest and never displayed again.') }}
            </p>

            <div class="mt-4">
                @php
                    $configured = [];
                    foreach (NodeCredentialField::cases() as $field) {
                        if (filled($credentialValues[$field->value] ?? null)) {
                            $configured[] = $field;
                        }
                    }
                @endphp

                @if (count($configured) === 0)
                    <x-ui.empty :title="__('No configured credentials')" :description="__('Save credentials from the create/edit form to mark them as configured.')"/>
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($configured as $field)
                            <li class="flex items-center justify-between gap-4 py-3 text-body-sm">
                                <span class="text-muted-foreground">{{ $field->label() }}</span>
                                <span class="font-mono text-small">••••••</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card :title="__('History')" class="lg:col-span-2">
            @if ($logs->isEmpty())
                <x-ui.empty :title="__('No activity yet')" :description="__('Actions performed on this node will appear here.')"/>
            @else
                <ul class="divide-y divide-border">
                    @foreach ($logs as $log)
                        <li class="flex flex-col gap-2 py-3 text-body-sm md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="font-medium">{{ $log->action }}</div>
                                <div class="text-small text-muted-foreground">
                                    {{ $log->created_at?->format('Y-m-d H:i:s') ?? '—' }}
                                    @if ($log->performer !== null)
                                        · {{ $log->performer->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                @php
                                    $logVariant = match ($log->status) {
                                        \Core\Nodes\Enums\NodeLogStatus::Success => 'success',
                                        \Core\Nodes\Enums\NodeLogStatus::Failed => 'danger',
                                        \Core\Nodes\Enums\NodeLogStatus::Skipped => 'neutral',
                                        default => 'warning',
                                    };
                                @endphp
                                <x-ui.badge :variant="$logVariant">{{ $log->status->value }}</x-ui.badge>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layout.admin>

