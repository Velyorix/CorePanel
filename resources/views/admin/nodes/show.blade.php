@php
    use Core\Nodes\Enums\NodeCredentialField;

    /** @var \Core\Nodes\Models\Node $node */
    $credentialValues = is_array($node->credentials) ? $node->credentials : [];
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
                    <form method="POST" action="{{ route('admin.nodes.test-connection.node', $node) }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm">
                            {{ __('Test connection') }}
                        </x-ui.button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

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
                        @if ($node->max_cpu_cores !== null || $node->max_ram_mb !== null || $node->max_disk_gb !== null)
                            <div class="mt-2 text-small text-muted-foreground">
                                @if ($node->max_cpu_cores !== null)
                                    {{ $node->allocatedResources()['cpu_cores'] }}/{{ $node->max_cpu_cores }} {{ __('CPU') }}
                                @endif
                                @if ($node->max_ram_mb !== null)
                                    @if ($node->max_cpu_cores !== null) · @endif
                                    {{ number_format($node->allocatedResources()['ram_mb']) }}/{{ number_format($node->max_ram_mb) }} {{ __('MB RAM') }}
                                @endif
                                @if ($node->max_disk_gb !== null)
                                    @if ($node->max_cpu_cores !== null || $node->max_ram_mb !== null) · @endif
                                    {{ $node->allocatedResources()['disk_gb'] }}/{{ $node->max_disk_gb }} {{ __('GB disk') }}
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
    </div>
</x-layout.admin>

