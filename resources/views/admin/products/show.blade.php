@php
    $statusVariant = match ($product->status) {
        \Core\Products\Enums\ProductStatus::Published => 'success',
        \Core\Products\Enums\ProductStatus::Draft => 'neutral',
        \Core\Products\Enums\ProductStatus::Archived => 'warning',
        default => 'neutral',
    };
@endphp

<x-layout.admin
    :title="$product->name"
    :page-heading="$product->name"
>
    <x-slot:subtitle>
        {{ __('Product catalog details and lifecycle.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Products'), 'url' => route('admin.products.index')],
            ['label' => $product->name],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('update', $product)
            @if ($product->status === \Core\Products\Enums\ProductStatus::Draft)
                <form method="POST" action="{{ route('admin.products.publish', $product) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Publish') }}
                    </x-ui.button>
                </form>
            @endif

            @if ($product->status === \Core\Products\Enums\ProductStatus::Published)
                <form method="POST" action="{{ route('admin.products.unpublish', $product) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Unpublish') }}
                    </x-ui.button>
                </form>
            @endif

            @if ($product->status !== \Core\Products\Enums\ProductStatus::Archived)
                <form method="POST" action="{{ route('admin.products.archive', $product) }}" onsubmit="return confirm(@js(__('Archive this product?')))">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Archive') }}
                    </x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.products.restore', $product) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Restore to draft') }}
                    </x-ui.button>
                </form>
            @endif

            <x-ui.button :href="route('admin.products.edit', $product)" variant="secondary" size="sm">
                {{ __('Edit') }}
            </x-ui.button>
        @endcan

        @can('delete', $product)
            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm(@js(__('Delete this product?')))">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger" size="sm">
                    {{ __('Delete') }}
                </x-ui.button>
            </form>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('status'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('status') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$statusVariant">{{ $product->status->label() }}</x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Type') }}</dt>
                    <dd>{{ $product->type->label() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Slug') }}</dt>
                    <dd class="font-mono text-small">{{ $product->slug }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Category') }}</dt>
                    <dd>
                        @if ($product->category)
                            <a href="{{ route('admin.product-categories.show', $product->category) }}" class="text-primary-700 hover:underline dark:text-primary-300">
                                {{ $product->category->name }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Module') }}</dt>
                    <dd>{{ $product->module ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Sort order') }}</dt>
                    <dd>{{ $product->sort_order }}</dd>
                </div>
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
                <p class="text-body-sm text-muted-foreground">{{ __('No pricing tiers configured.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-body-sm">
                        <thead>
                            <tr>
                                <th class="py-2 text-start font-medium">{{ __('Cycle') }}</th>
                                <th class="py-2 text-start font-medium">{{ __('Price') }}</th>
                                <th class="py-2 text-start font-medium">{{ __('Setup') }}</th>
                                <th class="py-2 text-start font-medium">{{ __('First payment') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($product->pricing as $tier)
                                <tr>
                                    <td class="py-2">
                                        {{ $tier->billing_cycle->label() }}
                                        @if (! $tier->is_enabled)
                                            <span class="text-small text-muted-foreground">({{ __('disabled') }})</span>
                                        @endif
                                        @if ($tier->custom_interval_days)
                                            <div class="text-small text-muted-foreground">{{ __(':days days', ['days' => $tier->custom_interval_days]) }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2">{{ $tier->price }}</td>
                                    <td class="py-2">{{ $tier->setup_fee }}</td>
                                    <td class="py-2">{{ $tier->firstPaymentTotal() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Options')">
            @if ($product->options->isEmpty())
                <p class="text-body-sm text-muted-foreground">{{ __('No configurable options.') }}</p>
            @else
                <ul class="space-y-2 text-body-sm">
                    @foreach ($product->options as $option)
                        <li>
                            <span class="font-medium">{{ $option->name }}</span>
                            <span class="text-muted-foreground">({{ $option->key }} · {{ $option->type->value }})</span>
                            @if ($option->required)
                                <x-ui.badge variant="warning">{{ __('Required') }}</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Addons')">
            @if ($product->addons->isEmpty())
                <p class="text-body-sm text-muted-foreground">{{ __('No addons.') }}</p>
            @else
                <ul class="space-y-2 text-body-sm">
                    @foreach ($product->addons as $addon)
                        <li>
                            <span class="font-medium">{{ $addon->name }}</span>
                            <span class="text-muted-foreground">
                                — {{ $addon->price }} / {{ $addon->billing_cycle->label() }}
                                @if ((float) $addon->setup_fee > 0)
                                    (+ {{ $addon->setup_fee }} {{ __('setup') }})
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        @if ($product->provisioningRules)
            <x-ui.card :title="__('Provisioning')" class="lg:col-span-2">
                <dl class="grid gap-3 text-body-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Auto-provision') }}</dt>
                        <dd>{{ $product->provisioningRules->auto_provision ? __('Yes') : __('No') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Welcome email') }}</dt>
                        <dd>{{ $product->provisioningRules->send_welcome_email ? __('Yes') : __('No') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Node group') }}</dt>
                        <dd>{{ $product->provisioningRules->nodeGroup?->name ?? ($product->provisioningRules->node_group_key ?: '—') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Email template') }}</dt>
                        <dd>{{ $product->provisioningRules->welcome_email_template ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        @endif
    </div>
</x-layout.admin>
