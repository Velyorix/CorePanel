@php
    $statusVariant = match ($article->status) {
        \Core\KnowledgeBase\Enums\KbArticleStatus::Published => 'success',
        \Core\KnowledgeBase\Enums\KbArticleStatus::Draft => 'primary',
        \Core\KnowledgeBase\Enums\KbArticleStatus::Archived => 'neutral',
    };
@endphp

<x-layout.admin
    :title="$article->title"
    :page-heading="$article->title"
>
    <x-slot:subtitle>{{ $article->slug }}</x-slot:subtitle>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Knowledge base'), 'url' => route('admin.kb-articles.index')],
            ['label' => $article->title],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('update', $article)
            <x-ui.button :href="route('admin.kb-articles.edit', $article)" variant="secondary" size="sm">{{ __('Edit') }}</x-ui.button>
            @if ($article->status === \Core\KnowledgeBase\Enums\KbArticleStatus::Draft || $article->status === \Core\KnowledgeBase\Enums\KbArticleStatus::Archived)
                <form method="POST" action="{{ route('admin.kb-articles.publish', $article) }}">@csrf
                    <x-ui.button type="submit" variant="primary" size="sm">{{ __('Publish') }}</x-ui.button>
                </form>
            @endif
            @if ($article->status === \Core\KnowledgeBase\Enums\KbArticleStatus::Published)
                <form method="POST" action="{{ route('admin.kb-articles.unpublish', $article) }}">@csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Unpublish') }}</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.kb-articles.archive', $article) }}">@csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Archive') }}</x-ui.button>
                </form>
            @endif
        @endcan
        @can('delete', $article)
            <form method="POST" action="{{ route('admin.kb-articles.destroy', $article) }}" onsubmit="return confirm(@js(__('Delete this article?')))">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">{{ __('Delete') }}</x-ui.button>
            </form>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6"><x-ui.alert variant="success">{{ session('status') }}</x-ui.alert></div>
    @endif
    @if ($errors->has('status'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('status') }}</x-ui.alert></div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd><x-ui.badge :variant="$statusVariant">{{ $article->status->label() }}</x-ui.badge></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Category') }}</dt>
                    <dd>{{ $article->category?->name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Author') }}</dt>
                    <dd>{{ $article->author?->name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Published') }}</dt>
                    <dd>{{ $article->published_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Content')" class="lg:col-span-2">
            @if ($article->excerpt)
                <p class="mb-4 text-body-sm text-muted-foreground">{{ $article->excerpt }}</p>
            @endif
            <div class="whitespace-pre-wrap text-body-sm text-foreground">{{ $article->body }}</div>
        </x-ui.card>
    </div>
</x-layout.admin>
