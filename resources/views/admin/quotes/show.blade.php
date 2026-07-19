@php
    $statusVariant = match ($quote->status) {
        \Core\Billing\Enums\QuoteStatus::Accepted, \Core\Billing\Enums\QuoteStatus::Converted => 'success',
        \Core\Billing\Enums\QuoteStatus::Sent => 'primary',
        \Core\Billing\Enums\QuoteStatus::Declined, \Core\Billing\Enums\QuoteStatus::Expired, \Core\Billing\Enums\QuoteStatus::Cancelled => 'danger',
        default => 'neutral',
    };

    $heading = $quote->quote_number ?: __('Draft quote #'.$quote->id);
@endphp

<x-layout.admin
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Quote details, line items, and lifecycle actions.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Quotes'), 'url' => route('admin.quotes.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.quotes.pdf', $quote)" variant="secondary" size="sm">
            {{ __('Download PDF') }}
        </x-ui.button>

        @can('manage', $quote)
            @if ($quote->status === \Core\Billing\Enums\QuoteStatus::Draft)
                <form method="POST" action="{{ route('admin.quotes.send', $quote) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Send quote') }}
                    </x-ui.button>
                </form>
            @endif

            @if ($quote->isConvertible())
                <form method="POST" action="{{ route('admin.quotes.convert', $quote) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Convert to invoice') }}
                    </x-ui.button>
                </form>
            @endif

            @if (in_array($quote->status, [\Core\Billing\Enums\QuoteStatus::Draft, \Core\Billing\Enums\QuoteStatus::Sent], true))
                <form method="POST" action="{{ route('admin.quotes.cancel', $quote) }}" onsubmit="return confirm(@js(__('Cancel this quote?')))">
                    @csrf
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Cancel quote') }}
                    </x-ui.button>
                </form>
            @endif
        @endcan
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

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$statusVariant">{{ $quote->status->label() }}</x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Currency') }}</dt>
                    <dd>{{ $quote->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Sent') }}</dt>
                    <dd>{{ $quote->sent_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Valid until') }}</dt>
                    <dd>{{ $quote->valid_until?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Accepted') }}</dt>
                    <dd>{{ $quote->accepted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                @if ($quote->convertedInvoice)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Converted invoice') }}</dt>
                        <dd>
                            <a href="{{ route('admin.invoices.show', $quote->convertedInvoice) }}" class="text-primary hover:underline">
                                {{ $quote->convertedInvoice->invoice_number ?: '#'.$quote->convertedInvoice->id }}
                            </a>
                        </dd>
                    </div>
                @endif
                @if ($quote->creator)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Created by') }}</dt>
                        <dd>{{ $quote->creator->name }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Client & billing')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Client') }}</dt>
                    <dd>
                        @if ($quote->client)
                            <a href="{{ route('admin.clients.show', $quote->client) }}" class="text-primary hover:underline">
                                {{ $quote->client->company_name ?: __('Client #'.$quote->client->id) }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Contact') }}</dt>
                    <dd>{{ $quote->contact_name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Email') }}</dt>
                    <dd>{{ $quote->contact_email ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Company') }}</dt>
                    <dd>{{ $quote->company_name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Address') }}</dt>
                    <dd class="text-end">
                        @if ($quote->address)
                            {{ $quote->address }}<br>
                            {{ $quote->postal_code }} {{ $quote->city }}<br>
                            {{ $quote->country }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if (filled($quote->notes))
                    <div class="border-t border-border pt-3">
                        <dt class="mb-1 text-muted-foreground">{{ __('Notes') }}</dt>
                        <dd class="whitespace-pre-wrap">{{ $quote->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>
    </div>

    <div class="mt-6">
        <x-ui.card :title="__('Line items')">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="border-b border-border text-start text-muted-foreground">
                            <th class="px-2 py-2 font-medium">{{ __('Product') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('Billing') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Qty') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Unit') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Setup') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($quote->items as $item)
                            <tr class="border-b border-border/60">
                                <td class="px-2 py-3">
                                    <div class="font-medium">{{ $item->product_name ?: ($item->product?->name ?? __('Product #'.$item->product_id)) }}</div>
                                    <div class="text-small text-muted-foreground">{{ $item->description }}</div>
                                </td>
                                <td class="px-2 py-3 text-muted-foreground">
                                    {{ $item->billing_cycle?->label() ?? '—' }}
                                </td>
                                <td class="px-2 py-3 text-end tabular-nums">{{ $item->quantity }}</td>
                                <td class="px-2 py-3 text-end tabular-nums">{{ number_format((float) $item->unit_price, 2, '.', ' ') }}</td>
                                <td class="px-2 py-3 text-end tabular-nums">{{ number_format((float) $item->setup_fee, 2, '.', ' ') }}</td>
                                <td class="px-2 py-3 text-end tabular-nums font-medium">{{ number_format((float) $item->line_total, 2, '.', ' ') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-2 py-6 text-center text-muted-foreground">
                                    {{ __('No line items on this quote.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <dl class="mt-4 space-y-2 border-t border-border pt-4 text-body-sm">
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
</x-layout.admin>
