<x-layout.client
    :title="$article->title"
    :page-heading="$article->title"
>
    <x-slot:subtitle>
        {{ $article->category?->name ?: __('Knowledge base') }}
    </x-slot:subtitle>
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
            ['label' => __('Knowledge base'), 'url' => route('client.kb.index')],
            ['label' => $article->title],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6">
        <x-ui.button :href="route('client.kb.index')" variant="ghost" size="sm">{{ __('Back to help center') }}</x-ui.button>
    </div>

    <x-ui.card>
        @if ($article->excerpt)
            <p class="mb-4 text-body-sm text-muted-foreground">{{ $article->excerpt }}</p>
        @endif
        <div class="whitespace-pre-wrap text-body-sm text-foreground">{{ $article->body }}</div>
        <p class="mt-6 text-small text-muted-foreground">
            {{ __('Updated :date', ['date' => $article->updated_at?->timezone(config('app.timezone'))->format('Y-m-d')]) }}
        </p>
    </x-ui.card>
</x-layout.client>
