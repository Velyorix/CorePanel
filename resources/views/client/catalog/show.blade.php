<x-layout.client
    :title="$product->name"
    :page-heading="$product->name"
>
    <x-slot:subtitle>
        {{ $product->description ?: __('Product details and available billing cycles.') }}
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
        @php
            $breadcrumbItems = [
                ['label' => __('Client'), 'url' => route('client.dashboard')],
                ['label' => __('Catalog'), 'url' => route('client.catalog.index')],
            ];

            if ($product->category) {
                $breadcrumbItems[] = [
                    'label' => $product->category->name,
                    'url' => route('client.catalog.category', $product->category->slug),
                ];
            }

            $breadcrumbItems[] = ['label' => $product->name];
        @endphp
        <x-ui.breadcrumb :items="$breadcrumbItems" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($product->category)
            <x-ui.button :href="route('client.catalog.category', $product->category->slug)" variant="secondary" size="sm">
                {{ __('Back to :category', ['category' => $product->category->name]) }}
            </x-ui.button>
        @else
            <x-ui.button :href="route('client.catalog.index')" variant="secondary" size="sm">
                {{ __('Back to catalog') }}
            </x-ui.button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Type') }}</dt>
                    <dd>{{ $product->type->label() }}</dd>
                </div>
                @if ($product->category)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Category') }}</dt>
                        <dd>{{ $product->category->name }}</dd>
                    </div>
                @endif
                @if ($product->description)
                    <div>
                        <dt class="mb-1 text-muted-foreground">{{ __('Description') }}</dt>
                        <dd class="whitespace-pre-wrap">{{ $product->description }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Pricing')">
            @if ($product->pricing->isEmpty())
                <p class="text-body-sm text-muted-foreground">{{ __('No pricing available.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-body-sm">
                        <thead>
                            <tr>
                                <th class="py-2 text-start font-medium">{{ __('Cycle') }}</th>
                                <th class="py-2 text-start font-medium">{{ __('Price') }}</th>
                                <th class="py-2 text-start font-medium">{{ __('Setup') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($product->pricing as $tier)
                                <tr>
                                    <td class="py-2">{{ $tier->billing_cycle->label() }}</td>
                                    <td class="py-2">{{ $tier->price }}</td>
                                    <td class="py-2">{{ $tier->setup_fee }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-6">
                <x-ui.button variant="primary" disabled title="{{ __('Configurator arrives in a later step.') }}">
                    {{ __('Configure') }}
                </x-ui.button>
                <p class="mt-2 text-small text-muted-foreground">
                    {{ __('Product configuration and add to cart will be available soon.') }}
                </p>
            </div>
        </x-ui.card>
    </div>
</x-layout.client>
