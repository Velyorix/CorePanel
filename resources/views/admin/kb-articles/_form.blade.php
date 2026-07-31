@php
    /** @var \Core\KnowledgeBase\Models\KbArticle|null $article */
    $article ??= null;
@endphp

<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input
            name="title"
            :label="__('Title')"
            :value="old('title', $article?->title)"
            class="sm:col-span-2"
            required
        />

        <x-ui.input
            name="slug"
            :label="__('Slug')"
            :value="old('slug', $article?->slug)"
            :hint="__('Leave empty to generate from the title.')"
        />

        <x-ui.select name="category_id" :label="__('Category')">
            <option value="">{{ __('No category') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $article?->category_id) === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </x-ui.select>

        <x-ui.input
            name="sort_order"
            type="number"
            :label="__('Sort order')"
            :value="old('sort_order', $article?->sort_order ?? 0)"
            min="0"
        />

        <div class="sm:col-span-2">
            <x-ui.input
                name="excerpt"
                :label="__('Excerpt')"
                :value="old('excerpt', $article?->excerpt)"
                :hint="__('Short summary shown in article lists.')"
            />
        </div>

        <div class="sm:col-span-2">
            <label for="body" class="mb-1.5 block text-body-sm font-medium text-foreground">{{ __('Body') }}</label>
            <textarea
                id="body"
                name="body"
                rows="12"
                required
                class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >{{ old('body', $article?->body) }}</textarea>
            @error('body')
                <p class="mt-1.5 text-small text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
