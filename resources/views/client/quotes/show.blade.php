@php
    $statusVariant = match ($quote->status) {
        \Core\Billing\Enums\QuoteStatus::Accepted, \Core\Billing\Enums\QuoteStatus::Converted => 'success',
        \Core\Billing\Enums\QuoteStatus::Sent => 'primary',
        \Core\Billing\Enums\QuoteStatus::Declined, \Core\Billing\Enums\QuoteStatus::Expired, \Core\Billing\Enums\QuoteStatus::Cancelled => 'danger',
        default => 'neutral',
    };

    $heading = $quote->quote_number ?: __('Quote #:id', ['id' => $quote->id]);
@endphp

<x-layout.client
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Quote details and response.') }}
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
            ['label' => __('Quotes'), 'url' => route('client.quotes.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
        <x-ui.button :href="route('client.quotes.index')" variant="secondary" size="sm">
            {{ __('Back to quotes') }}
        </x-ui.button>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button :href="route('client.quotes.pdf', $quote)" variant="secondary" size="sm">
                {{ __('Download PDF') }}
            </x-ui.button>

            @if ($quote->status === \Core\Billing\Enums\QuoteStatus::Sent)
                <form method="POST" action="{{ route('client.quotes.accept', $quote) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Accept quote') }}
                    </x-ui.button>
                </form>
                <form method="POST" action="{{ route('client.quotes.decline', $quote) }}" onsubmit="return confirm(@js(__('Decline this quote?')))">
                    @csrf
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Decline quote') }}
                    </x-ui.button>
                </form>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->has('quote'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('quote') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('Quote status')">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <span class="text-body-sm text-muted-foreground">{{ __('Current status') }}</span>
                    <x-ui.badge :variant="$statusVariant">{{ $quote->status->label() }}</x-ui.badge>
                </div>

                <dl class="space-y-3 text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Sent') }}</dt>
                        <dd>{{ $quote->sent_at?->timezone(config('app.timezone'))->format('Y-m-d') ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Valid until') }}</dt>
                        <dd>{{ $quote->valid_until?->timezone(config('app.timezone'))->format('Y-m-d') ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Items')">
                <ul class="space-y-4">
                    @foreach ($quote->items as $item)
                        <li class="border-b border-border pb-4 last:border-0 last:pb-0">
                            <div class="flex justify-between gap-4 text-body-sm font-medium">
                                <span>{{ $item->product_name ?: ($item->product?->name ?? __('Product')) }}</span>
                                <span class="tabular-nums">{{ number_format((float) $item->line_total, 2, '.', ' ') }}</span>
                            </div>
                            <p class="mt-1 text-small text-muted-foreground">
                                {{ $item->billing_cycle?->label() ?? '—' }}
                                · {{ __('Qty') }} {{ $item->quantity }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Summary')">
                <dl class="space-y-3 text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Subtotal') }}</dt>
                        <dd class="tabular-nums">{{ number_format((float) $quote->subtotal, 2, '.', ' ') }} {{ $quote->currency }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Tax') }}</dt>
                        <dd class="tabular-nums">{{ number_format((float) $quote->tax_amount, 2, '.', ' ') }} {{ $quote->currency }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 text-body font-medium">
                        <dt>{{ __('Total') }}</dt>
                        <dd class="tabular-nums">{{ number_format((float) $quote->total_amount, 2, '.', ' ') }} {{ $quote->currency }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-layout.client>
