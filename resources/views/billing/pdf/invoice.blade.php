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
                <div class="muted">{{ __('Status') }}: {{ $invoice->status->label() }}</div>
                @if ($invoice->issued_at)
                    <div class="muted">{{ __('Issued') }}: {{ $invoice->issued_at->toDateString() }}</div>
                @endif
                @if ($invoice->due_at)
                    <div class="muted">{{ __('Due') }}: {{ $invoice->due_at->toDateString() }}</div>
                @endif
                <div class="muted">{{ __('Currency') }}: {{ $invoice->currency }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="box-title">{{ __('Bill to') }}</div>
                <div><strong>{{ $invoice->company_name ?: $invoice->contact_name }}</strong></div>
                @if ($invoice->company_name && $invoice->contact_name)
                    <div>{{ $invoice->contact_name }}</div>
                @endif
                @if ($invoice->address)
                    <div>{{ $invoice->address }}</div>
                @endif
                @if ($invoice->postal_code || $invoice->city)
                    <div>{{ trim(($invoice->postal_code ?? '').' '.($invoice->city ?? '')) }}</div>
                @endif
                @if ($invoice->country)
                    <div>{{ $invoice->country }}</div>
                @endif
                @if ($invoice->vat_number)
                    <div class="muted">{{ __('VAT') }}: {{ $invoice->vat_number }}</div>
                @endif
                @if ($invoice->contact_email)
                    <div class="muted">{{ $invoice->contact_email }}</div>
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
            @forelse ($invoice->items as $item)
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
            <td class="value">{{ $invoice->subtotal }}</td>
        </tr>
        @if ((float) $invoice->discount_amount > 0)
            <tr>
                <td class="label">{{ __('Discount') }}</td>
                <td class="value">-{{ $invoice->discount_amount }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">{{ __('Tax') }}</td>
            <td class="value">{{ $invoice->tax_amount }}</td>
        </tr>
        <tr class="grand">
            <td>{{ __('Total') }}</td>
            <td class="value">{{ $invoice->total_amount }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Amount due') }}</td>
            <td class="value">{{ $invoice->amountDue() }} {{ $invoice->currency }}</td>
        </tr>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <div class="box-title">{{ __('Notes') }}</div>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif
@endsection
