<x-layout.client
    :title="__('Knowledge base')"
    :page-heading="__('Help center')"
>
    <x-slot:subtitle>{{ __('Browse guides and answers before opening a ticket.') }}</x-slot:subtitle>
    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            <x-ui.theme-toggle />
            @auth
                <span class="hidden text-body-sm text-muted-foreground sm:inline">{{ auth()->user()->email }}</span>
            @endauth
        </div>
    </x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Client'), 'url' => route('client.dashboard')],
            ['label' => __('Knowledge base')],
        ]" />
    </x-slot:breadcrumbs>

    <x-ui.table :paginator="$articles">
        <x-slot:filters>
            <form method="GET" action="{{ route('client.kb.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Search articles…')" />
                </div>
                <div class="min-w-44">
                    <x-ui.select name="category_id" :label="__('Category')">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Search') }}</x-ui.button>
                @if (filled($filters['q']) || $filters['category_id'] !== null)
                    <x-ui.button :href="route('client.kb.index')" variant="ghost" size="sm">{{ __('Clear') }}</x-ui.button>
                @endif
            </form>
        </x-slot:filters>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Article') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Category') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>
        <x-slot:empty>
            <tr>
                <td colspan="3" class="p-4">
                    <x-ui.empty
                        :title="__('No articles found')"
                        :description="filled($filters['q']) || $filters['category_id'] !== null
                            ? __('Try another search or category.')
                            : __('Help articles will appear here once published.')"
                    />
                </td>
            </tr>
        </x-slot:empty>
        @foreach ($articles as $article)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $article->title }}</div>
                    @if ($article->excerpt)
                        <div class="text-small text-muted-foreground">{{ $article->excerpt }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $article->category?->name ?: '—' }}</td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('client.kb.show', $article->slug)" variant="ghost" size="sm">{{ __('Read') }}</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.client>
