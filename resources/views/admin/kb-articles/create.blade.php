<x-layout.admin
    :title="__('Create article')"
    :page-heading="__('Create article')"
>
    <x-slot:topbar><x-admin.topbar /></x-slot:topbar>
    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Knowledge base'), 'url' => route('admin.kb-articles.index')],
            ['label' => __('Create')],
        ]" />
    </x-slot:breadcrumbs>

    @if ($errors->has('article'))
        <div class="mb-6"><x-ui.alert variant="danger">{{ $errors->first('article') }}</x-ui.alert></div>
    @endif

    <x-ui.card :title="__('Article')">
        <form method="POST" action="{{ route('admin.kb-articles.store') }}" class="space-y-6">
            @csrf
            @include('admin.kb-articles._form')
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="primary" size="sm">{{ __('Create article') }}</x-ui.button>
                <x-ui.button :href="route('admin.kb-articles.index')" variant="ghost" size="sm">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
