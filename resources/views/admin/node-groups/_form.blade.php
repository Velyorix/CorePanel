@php
    /** @var \Core\Nodes\Models\NodeGroup|null $group */
    $group ??= null;
    $selectedNodeIds = old('node_ids', $selectedNodeIds ?? []);
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input
        name="name"
        :label="__('Name')"
        :value="old('name', $group?->name)"
        class="sm:col-span-2"
        required
    />

    <x-ui.input
        name="key"
        :label="__('Key')"
        :value="old('key', $group?->key)"
        :hint="__('Leave empty to generate from the name.')"
    />

    <x-ui.input
        name="location"
        :label="__('Location')"
        :value="old('location', $group?->location)"
        :hint="__('Datacenter, region, or country.')"
    />

    <x-ui.select name="type" :label="__('Usage type')" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected((string) old('type', $group?->type?->value ?? \Core\Nodes\Enums\NodeGroupType::General->value) === $type->value)>
                {{ $type->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select name="status" :label="__('Status')" required>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected((string) old('status', $group?->status?->value ?? \Core\Nodes\Enums\NodeGroupStatus::Active->value) === $status->value)>
                {{ $status->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.input
        name="sort_order"
        type="number"
        :label="__('Sort order')"
        :value="old('sort_order', $group?->sort_order ?? 0)"
        min="0"
    />

    <div class="sm:col-span-2">
        <label for="description" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Description') }}</label>
        <textarea
            id="description"
            name="description"
            rows="3"
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >{{ old('description', $group?->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="node_ids" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Assigned servers') }}</label>
        <select
            id="node_ids"
            name="node_ids[]"
            multiple
            size="8"
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            @foreach ($availableNodes as $node)
                <option value="{{ $node->id }}" @selected(in_array($node->id, $selectedNodeIds, true))>
                    {{ $node->name }} — {{ $node->hostname }}
                </option>
            @endforeach
        </select>
        <p class="mt-1.5 text-small text-muted-foreground">{{ __('Hold Ctrl or Cmd to select multiple servers.') }}</p>
        @error('node_ids')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
        @error('node_ids.*')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>
</div>
