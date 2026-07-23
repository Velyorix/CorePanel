<x-layout.admin
    :title="__('KB categories')"
    :page-heading="__('Knowledge base categories')"
>
    <x-slot:subtitle>{{ __('Organize help articles into categories.') }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Knowledge base'), 'url' => route('admin.kb-articles.index')],
            ['label' => __('Categories')],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.kb-articles.index')" variant="secondary" size="sm">{{ __('Articles') }}</x-ui.button>
        @can('create', Core\KnowledgeBase\Models\KbCategory::class)
            <x-ui.button :href="route('admin.kb-categories.create')" variant="primary" size="sm">{{ __('Create category') }}</x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6"><x-ui.alert variant="success">{{ session('status') }}</x-ui.alert></div>
    @endif
    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$categories">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.kb-categories.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input name="q" :label="__('Search')" :value="$filters['q']" />
                </div>
                <div class="min-w-40">
                    <x-ui.select name="status" :label="__('Status')">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:filters>
        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="name">{{ __('Name') }}</x-ui.table-heading>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Articles') }}</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>
        <x-slot:empty>
            <tr><td colspan="4" class="p-4"><x-ui.empty :title="__('No categories yet')" /></td></tr>
        </x-slot:empty>
        @foreach ($categories as $category)
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $category->name }}</div>
                    <div class="text-small text-muted-foreground">{{ $category->slug }}</div>
                </td>
                <td class="px-4 py-3"><x-ui.badge variant="neutral">{{ $category->status->label() }}</x-ui.badge></td>
                <td class="px-4 py-3 tabular-nums">{{ $category->articles_count }}</td>
                <td class="px-4 py-3 text-end">
                    @can('update', $category)
                        <x-ui.button :href="route('admin.kb-categories.edit', $category)" variant="ghost" size="sm">{{ __('Edit') }}</x-ui.button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
