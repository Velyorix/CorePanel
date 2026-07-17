@php
    /** @var \Core\Products\Models\ProductCategory|null $category */
    $category ??= null;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input
        name="name"
        :label="__('Name')"
        :value="old('name', $category?->name)"
        class="sm:col-span-2"
        required
    />

    <x-ui.input
        name="slug"
        :label="__('Slug')"
        :value="old('slug', $category?->slug)"
        :hint="__('Leave empty to generate from the name.')"
    />

    <x-ui.select name="status" :label="__('Status')" required>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected((string) old('status', $category?->status?->value ?? \Core\Products\Enums\ProductCategoryStatus::Active->value) === $status->value)>
                {{ $status->label() }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.select name="parent_id" :label="__('Parent category')">
        <option value="">{{ __('None') }}</option>
        @foreach ($parentCategories as $parent)
            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $parent->id)>
                {{ $parent->name }}
            </option>
        @endforeach
    </x-ui.select>

    <x-ui.input
        name="sort_order"
        type="number"
        :label="__('Sort order')"
        :value="old('sort_order', $category?->sort_order ?? 0)"
        min="0"
    />

    <div class="sm:col-span-2">
        <label for="description" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Description') }}</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >{{ old('description', $category?->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
        @enderror
    </div>
</div>
