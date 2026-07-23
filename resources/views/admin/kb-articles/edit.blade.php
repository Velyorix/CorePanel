<x-layout.admin
    :title="__('Edit article')"
    :page-heading="__('Edit article')"
>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Knowledge base'), 'url' => route('admin.kb-articles.index')],
            ['label' => $article->title, 'url' => route('admin.kb-articles.show', $article)],
            ['label' => __('Edit')],
        ]" />
    </x-slot:breadcrumbs>

    @if ($errors->has('article'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('article') }}</x-ui.alert></div>
    @endif

    <x-ui.card :title="__('Article')">
        <form method="POST" action="{{ route('admin.kb-articles.update', $article) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.kb-articles._form', ['article' => $article])
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="primary" size="sm">{{ __('Save changes') }}</x-ui.button>
                <x-ui.button :href="route('admin.kb-articles.show', $article)" variant="ghost" size="sm">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
