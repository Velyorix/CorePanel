@php
    /** @var \Core\Nodes\Models\Node|null $node */
    $node ??= null;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input
        name="name"
        :label="__('Name')"
        :value="old('name', $node?->name)"
        class="sm:col-span-2"
        required
    />

    <x-ui.input
        name="hostname"
        :label="__('Hostname')"
        :value="old('hostname', $node?->hostname)"
        :hint="__('FQDN or host identifier used for provisioning.')"
        class="sm:col-span-2"
        required
    />

    <x-ui.select name="type" :label="__('Server type')" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected((string) old('type', $node?->type?->value ?? \Core\Nodes\Enums\NodeType::General->value) === $type->value)>
                {{ $type->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select name="module" :label="__('Provider module')">
        <option value="">{{ __('None') }}</option>
        @foreach ($modules as $key => $label)
            <option value="{{ $key }}" @selected((string) old('module', $node?->module) === $key)>
                {{ $label }} ({{ $key }})
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.input
        name="ip_address"
        :label="__('IP address')"
        :value="old('ip_address', $node?->ip_address)"
    />

    <x-ui.input
        name="api_url"
        type="url"
        :label="__('API URL')"
        :value="old('api_url', $node?->api_url)"
        :hint="__('Remote control panel or API endpoint.')"
    />

    <x-ui.select name="node_group_id" :label="__('Node group')">
        <option value="">{{ __('None') }}</option>
        @foreach ($groups as $group)
            <option value="{{ $group->id }}" @selected((string) old('node_group_id', $node?->node_group_id) === (string) $group->id)>
                {{ $group->name }} ({{ $group->key }})
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select name="status" :label="__('Status')">
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected((string) old('status', $node?->status?->value ?? \Core\Nodes\Enums\NodeStatus::Active->value) === $status->value)>
                {{ $status->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.input
        name="max_services"
        type="number"
        :label="__('Max services')"
        :value="old('max_services', $node?->max_services)"
        min="0"
        :hint="__('Leave empty for unlimited capacity.')"
    />

    <x-ui.input
        name="sort_order"
        type="number"
        :label="__('Sort order')"
        :value="old('sort_order', $node?->sort_order ?? 0)"
        min="0"
    />

    @include('admin.nodes._credentials')
</div>
