@php
    /** @var \Core\Nodes\Models\Node|null $node */
    $node ??= null;
@endphp

<div class="sm:col-span-2">
    <h3 class="text-body-sm font-medium text-foreground">{{ __('Capacity limits') }}</h3>
    <p class="mt-1 text-small text-muted-foreground">
        {{ __('Leave empty for unlimited capacity. Used during node selection and provisioning.') }}
    </p>
</div>

<x-ui.input
    name="max_services"
    type="number"
    :label="__('Max services')"
    :value="old('max_services', $node?->max_services)"
    min="0"
/>

<x-ui.input
    name="max_cpu_cores"
    type="number"
    :label="__('Max CPU cores')"
    :value="old('max_cpu_cores', $node?->max_cpu_cores)"
    min="0"
/>

<x-ui.input
    name="max_ram_mb"
    type="number"
    :label="__('Max RAM (MB)')"
    :value="old('max_ram_mb', $node?->max_ram_mb)"
    min="0"
    :hint="__('Megabytes of memory available for allocation.')"
/>

<x-ui.input
    name="max_disk_gb"
    type="number"
    :label="__('Max disk (GB)')"
    :value="old('max_disk_gb', $node?->max_disk_gb)"
    min="0"
    :hint="__('Gigabytes of storage available for allocation.')"
/>

<x-ui.input
    name="max_bandwidth_mbps"
    type="number"
    :label="__('Max bandwidth (Mbps)')"
    :value="old('max_bandwidth_mbps', $node?->max_bandwidth_mbps)"
    min="0"
    :hint="__('Peak inbound/outbound bandwidth available for allocation.')"
/>

@php
    $allocationWeight = old(
        'allocation_weight',
        is_array($node?->config['allocation'] ?? null)
            ? ($node->config['allocation']['weight'] ?? null)
            : null,
    );
@endphp

<x-ui.input
    name="allocation_weight"
    type="number"
    :label="__('Allocation weight')"
    :value="$allocationWeight ?? config('corepanel.nodes.allocation.load_balancing.default_weight', 100)"
    min="1"
    max="1000"
    :hint="__('Higher values receive more provisioning assignments within the same utilization band.')"
/>

@php
    $allocationIsFallback = old(
        'allocation_is_fallback',
        is_array($node?->config['allocation'] ?? null)
            ? (bool) ($node->config['allocation']['is_fallback'] ?? false)
            : false,
    );
@endphp

<label class="flex items-start gap-3 sm:col-span-2">
    <input
        type="checkbox"
        name="allocation_is_fallback"
        value="1"
        @checked((bool) $allocationIsFallback)
        class="mt-1 rounded border-border"
    />
    <span>
        <span class="text-body-sm font-medium text-foreground">{{ __('Overflow fallback node') }}</span>
        <span class="mt-1 block text-small text-muted-foreground">
            {{ __('Accepts provisioning overflow when primary nodes in the group are overloaded.') }}
        </span>
    </span>
</label>
