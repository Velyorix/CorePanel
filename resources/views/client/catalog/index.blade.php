<x-layout.client
    :title="__('Catalog')"
    :page-heading="__('Catalog')"
>
    <x-slot:subtitle>
        {{ __('Browse products by category and choose a plan to configure.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            <x-ui.theme-toggle />
            @auth
                <span class="hidden text-body-sm text-muted-foreground sm:inline">
                    {{ auth()->user()->email }}
                </span>
            @endauth
        </div>
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Client'), 'url' => route('client.dashboard')],
            ['label' => __('Catalog')],
        ]" />
    </x-slot:breadcrumbs>

    @if ($categories->isEmpty())
        <x-ui.empty
            :title="__('No categories available')"
            :description="__('Published product categories will appear here once they are ready.')"
        />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($categories as $category)
                <x-ui.card :title="$category->name">
                    @if ($category->description)
                        <p class="text-body-sm text-muted-foreground">{{ $category->description }}</p>
                    @endif

                    <p class="mt-3 text-small text-muted-foreground">
                        {{ trans_choice(':count product|:count products', $category->published_products_count, ['count' => $category->published_products_count]) }}
                    </p>

                    <div class="mt-4">
                        <x-ui.button :href="route('client.catalog.category', $category->slug)" variant="primary" size="sm">
                            {{ __('Browse') }}
                        </x-ui.button>
                    </div>

                    @if ($category->children->isNotEmpty())
                        <ul class="mt-4 space-y-1 border-t border-border pt-3 text-body-sm">
                            @foreach ($category->children as $child)
                                <li>
                                    <a
                                        href="{{ route('client.catalog.category', $child->slug) }}"
                                        class="text-primary-700 hover:underline dark:text-primary-300"
                                    >
                                        {{ $child->name }}
                                    </a>
                                    <span class="text-small text-muted-foreground">
                                        ({{ $child->published_products_count }})
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layout.client>
