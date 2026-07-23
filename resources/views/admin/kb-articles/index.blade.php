<x-layout.admin
    :title="__('Knowledge base')"
    :page-heading="__('Knowledge base articles')"
>
    <x-slot:subtitle>
        {{ __('Create and publish help articles for clients.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Knowledge base')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.kb-categories.index')" variant="secondary" size="sm">
            {{ __('Categories') }}
        </x-ui.button>
        @can('create', Core\KnowledgeBase\Models\KbArticle::class)
            <x-ui.button :href="route('admin.kb-articles.create')" variant="primary" size="sm">
                {{ __('Create article') }}
            </x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$articles">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.kb-articles.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" :placeholder="__('Title, slug, body…')" />
                </div>
                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="min-w-40">
                    <x-ui.select name="category_id" :label="__('Category')">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="title">{{ __('Title') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Category') }}</th>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <x-ui.table-heading sort="updated_at">{{ __('Updated') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="5" class="p-4">
                    <x-ui.empty :title="__('No articles yet')" :description="__('Create your first help article.')" />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($articles as $article)
            @php
                $statusVariant = match ($article->status) {
                    \Core\KnowledgeBase\Enums\KbArticleStatus::Published => 'success',
                    \Core\KnowledgeBase\Enums\KbArticleStatus::Draft => 'primary',
                    \Core\KnowledgeBase\Enums\KbArticleStatus::Archived => 'neutral',
                };
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $article->title }}</div>
                    <div class="text-small text-muted-foreground">{{ $article->slug }}</div>
                </td>
                <td class="px-4 py-3 text-muted-foreground">{{ $article->category?->name ?: '—' }}</td>
                <td class="px-4 py-3"><x-ui.badge :variant="$statusVariant">{{ $article->status->label() }}</x-ui.badge></td>
                <td class="px-4 py-3 text-muted-foreground">{{ $article->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.kb-articles.show', $article)" variant="ghost" size="sm">{{ __('View') }}</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
