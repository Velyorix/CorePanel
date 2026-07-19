@extends('billing.pdf.layout')

@section('content')
    <table class="header">
        <tr>
            <td>
                <div class="seller-name">{{ $seller['name'] }}</div>
                @if ($seller['address'])
                    <div>{{ $seller['address'] }}</div>
                @endif
                @if ($seller['postal_code'] || $seller['city'])
                    <div>{{ trim(($seller['postal_code'] ?? '').' '.($seller['city'] ?? '')) }}</div>
                @endif
                @if ($seller['country'])
                    <div>{{ $seller['country'] }}</div>
                @endif
                @if ($seller['vat_number'])
                    <div class="muted">{{ __('VAT') }}: {{ $seller['vat_number'] }}</div>
                @endif
                @if ($seller['email'])
                    <div class="muted">{{ $seller['email'] }}</div>
                @endif
                @if ($seller['phone'])
                    <div class="muted">{{ $seller['phone'] }}</div>
                @endif
            </td>
            <td class="meta">
                <h1>{{ $documentTitle }}</h1>
                <div><strong>{{ $documentNumber }}</strong></div>
                <div class="muted">{{ __('Status') }}: {{ $quote->status->label() }}</div>
                @if ($quote->sent_at)
                    <div class="muted">{{ __('Sent') }}: {{ $quote->sent_at->toDateString() }}</div>
                @endif
                @if ($quote->valid_until)
                    <div class="muted">{{ __('Valid until') }}: {{ $quote->valid_until->toDateString() }}</div>
                @endif
                <div class="muted">{{ __('Currency') }}: {{ $quote->currency }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="box-title">{{ __('Quote for') }}</div>
                <div><strong>{{ $quote->company_name ?: $quote->contact_name }}</strong></div>
                @if ($quote->company_name && $quote->contact_name)
                    <div>{{ $quote->contact_name }}</div>
                @endif
                @if ($quote->address)
                    <div>{{ $quote->address }}</div>
                @endif
                @if ($quote->postal_code || $quote->city)
                    <div>{{ trim(($quote->postal_code ?? '').' '.($quote->city ?? '')) }}</div>
                @endif
                @if ($quote->country)
                    <div>{{ $quote->country }}</div>
                @endif
                @if ($quote->vat_number)
                    <div class="muted">{{ __('VAT') }}: {{ $quote->vat_number }}</div>
                @endif
                @if ($quote->contact_email)
                    <div class="muted">{{ $quote->contact_email }}</div>
                @endif
            </td>
            <td></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Unit') }}</th>
                <th class="num">{{ __('Setup') }}</th>
                <th class="num">{{ __('Tax') }}</th>
                <th class="num">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($quote->items as $item)
                <tr>
                    <td>
                        {{ $item->description ?: $item->product_name }}
                        @if ($item->billing_cycle)
                            <div class="muted">{{ $item->billing_cycle->label() }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $item->unit_price }}</td>
                    <td class="num">{{ $item->setup_fee }}</td>
                    <td class="num">{{ $item->tax_amount }}</td>
                    <td class="num">{{ $item->line_total }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">{{ __('No line items.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">{{ __('Subtotal') }}</td>
            <td class="value">{{ $quote->subtotal }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Tax') }}</td>
            <td class="value">{{ $quote->tax_amount }}</td>
        </tr>
        <tr class="grand">
            <td>{{ __('Total') }}</td>
            <td class="value">{{ $quote->total_amount }} {{ $quote->currency }}</td>
        </tr>
    </table>

    @if ($quote->notes)
        <div class="notes">
            <div class="box-title">{{ __('Notes') }}</div>
            <div>{{ $quote->notes }}</div>
        </div>
    @endif
@endsection
