<x-layout.client
    :title="$category->name"
    :page-heading="$category->name"
>
    <x-slot:subtitle>
        {{ $category->description ?: __('Published products in this category.') }}
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
            ['label' => __('Catalog'), 'url' => route('client.catalog.index')],
            ['label' => $category->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6">
        <x-ui.button :href="route('client.catalog.index')" variant="secondary" size="sm">
            {{ __('All categories') }}
        </x-ui.button>
    </div>

    @if ($products->isEmpty())
        <x-ui.empty
            :title="__('No products in this category')"
            :description="__('Check back later or browse another category.')"
        />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($products as $product)
                @php
                    $monthly = $product->pricingFor(\Core\Products\Enums\BillingCycle::Monthly);
                    $fromPrice = $product->pricing->sortBy('price')->first();
                @endphp
                <x-ui.card :title="$product->name">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge variant="neutral">{{ $product->type->label() }}</x-ui.badge>
                    </div>

                    @if ($product->description)
                        <p class="mt-3 line-clamp-3 text-body-sm text-muted-foreground">
                            {{ $product->description }}
                        </p>
                    @endif

                    <p class="mt-4 text-h3 font-semibold tracking-tight">
                        @if ($monthly)
                            {{ $monthly->price }}
                            <span class="text-body-sm font-normal text-muted-foreground">/ {{ __('mo') }}</span>
                        @elseif ($fromPrice)
                            {{ __('From') }} {{ $fromPrice->price }}
                        @else
                            {{ __('See details') }}
                        @endif
                    </p>

                    <div class="mt-4">
                        <x-ui.button :href="route('client.catalog.products.show', $product->slug)" variant="primary" size="sm">
                            {{ __('View product') }}
                        </x-ui.button>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $products->links() }}
        </div>
    @endif
</x-layout.client>
