@php
    $statusVariant = match ($invoice->status) {
        \Core\Billing\Enums\InvoiceStatus::Paid => 'success',
        \Core\Billing\Enums\InvoiceStatus::Unpaid => 'primary',
        \Core\Billing\Enums\InvoiceStatus::Overdue => 'danger',
        \Core\Billing\Enums\InvoiceStatus::Cancelled, \Core\Billing\Enums\InvoiceStatus::Refunded => 'neutral',
        default => 'neutral',
    };

    $heading = $invoice->invoice_number ?: __('Draft invoice #'.$invoice->id);
@endphp

<x-layout.admin
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Invoice details, line items, and payment history.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Invoices'), 'url' => route('admin.invoices.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <x-ui.button :href="route('admin.invoices.pdf', $invoice)" variant="secondary" size="sm">
            {{ __('Download PDF') }}
        </x-ui.button>

        @can('manage', $invoice)
            @if ($invoice->status === \Core\Billing\Enums\InvoiceStatus::Draft)
                <form method="POST" action="{{ route('admin.invoices.issue', $invoice) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Issue invoice') }}
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

    @if ($errors->has('invoice'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('invoice') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$statusVariant">{{ $invoice->status->label() }}</x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Currency') }}</dt>
                    <dd>{{ $invoice->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Issued') }}</dt>
                    <dd>{{ $invoice->issued_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Due') }}</dt>
                    <dd>{{ $invoice->due_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Paid') }}</dt>
                    <dd>{{ $invoice->paid_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Amount due') }}</dt>
                    <dd class="tabular-nums font-medium">{{ number_format((float) $invoice->amountDue(), 2, '.', ' ') }} {{ $invoice->currency }}</dd>
                </div>
                @if ($invoice->order)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Order') }}</dt>
                        <dd>
                            <a href="{{ route('admin.orders.show', $invoice->order) }}" class="text-primary hover:underline">
                                {{ $invoice->order->order_number ?: '#'.$invoice->order->id }}
                            </a>
                        </dd>
                    </div>
                @endif
                @if ($invoice->creator)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Created by') }}</dt>
                        <dd>{{ $invoice->creator->name }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Client & billing')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Client') }}</dt>
                    <dd>
                        @if ($invoice->client)
                            <a href="{{ route('admin.clients.show', $invoice->client) }}" class="text-primary hover:underline">
                                {{ $invoice->client->company_name ?: __('Client #'.$invoice->client->id) }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Contact') }}</dt>
                    <dd>{{ $invoice->contact_name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Email') }}</dt>
                    <dd>{{ $invoice->contact_email ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Company') }}</dt>
                    <dd>{{ $invoice->company_name ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Address') }}</dt>
                    <dd class="text-end">
                        @if ($invoice->address)
                            {{ $invoice->address }}<br>
                            {{ $invoice->postal_code }} {{ $invoice->city }}<br>
                            {{ $invoice->country }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if (filled($invoice->notes))
                    <div class="border-t border-border pt-3">
                        <dt class="mb-1 text-muted-foreground">{{ __('Notes') }}</dt>
                        <dd class="whitespace-pre-wrap">{{ $invoice->notes }}</dd>
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
                        @forelse ($invoice->items as $item)
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
                                    {{ __('No line items on this invoice.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <dl class="mt-4 space-y-2 border-t border-border pt-4 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Subtotal') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $invoice->subtotal, 2, '.', ' ') }} {{ $invoice->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Discount') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $invoice->discount_amount, 2, '.', ' ') }} {{ $invoice->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Tax') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $invoice->tax_amount, 2, '.', ' ') }} {{ $invoice->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4 text-body font-medium">
                    <dt>{{ __('Total') }}</dt>
                    <dd class="tabular-nums">{{ number_format((float) $invoice->total_amount, 2, '.', ' ') }} {{ $invoice->currency }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>

    <div class="mt-6">
        <x-ui.card :title="__('Payments')">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="border-b border-border text-start text-muted-foreground">
                            <th class="px-2 py-2 font-medium">{{ __('Method') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('Status') }}</th>
                            <th class="px-2 py-2 font-medium text-end">{{ __('Amount') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('Reference') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('Paid') }}</th>
                            <th class="px-2 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            @php
                                $paymentVariant = match ($payment->status) {
                                    \Core\Billing\Enums\PaymentStatus::Completed => 'success',
                                    \Core\Billing\Enums\PaymentStatus::Pending => 'warning',
                                    \Core\Billing\Enums\PaymentStatus::Failed => 'danger',
                                    default => 'neutral',
                                };
                            @endphp
                            <tr class="border-b border-border/60">
                                <td class="px-2 py-3">{{ $payment->method }}</td>
                                <td class="px-2 py-3">
                                    <x-ui.badge :variant="$paymentVariant">{{ $payment->status->label() }}</x-ui.badge>
                                </td>
                                <td class="px-2 py-3 text-end tabular-nums">{{ number_format((float) $payment->amount, 2, '.', ' ') }} {{ $payment->currency }}</td>
                                <td class="px-2 py-3 text-muted-foreground">{{ $payment->gateway_reference ?: '—' }}</td>
                                <td class="px-2 py-3 text-muted-foreground">{{ $payment->paid_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</td>
                                <td class="px-2 py-3 text-end">
                                    @can('manage', $payment)
                                        @if ($payment->status === \Core\Billing\Enums\PaymentStatus::Pending)
                                            <form method="POST" action="{{ route('admin.payments.complete', $payment) }}" class="inline">
                                                @csrf
                                                <x-ui.button type="submit" variant="primary" size="sm">
                                                    {{ __('Complete') }}
                                                </x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.payments.fail', $payment) }}" class="inline">
                                                @csrf
                                                <x-ui.button type="submit" variant="danger" size="sm">
                                                    {{ __('Fail') }}
                                                </x-ui.button>
                                            </form>
                                        @else
                                            <x-ui.button :href="route('admin.payments.show', $payment)" variant="ghost" size="sm">
                                                {{ __('View') }}
                                            </x-ui.button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-2 py-6 text-center text-muted-foreground">
                                    {{ __('No payments recorded for this invoice.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</x-layout.admin>
