@php
    $statusVariant = match ($invoice->status) {
        \Core\Billing\Enums\InvoiceStatus::Paid => 'success',
        \Core\Billing\Enums\InvoiceStatus::Unpaid => 'primary',
        \Core\Billing\Enums\InvoiceStatus::Overdue => 'danger',
        default => 'neutral',
    };

    $heading = $invoice->invoice_number ?: __('Invoice #:id', ['id' => $invoice->id]);
    $pendingPayments = $invoice->payments->where('status', \Core\Billing\Enums\PaymentStatus::Pending);
@endphp

<x-layout.client
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Invoice details and payment status.') }}
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
            ['label' => __('Invoices'), 'url' => route('client.invoices.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
        <x-ui.button :href="route('client.invoices.index')" variant="secondary" size="sm">
            {{ __('Back to invoices') }}
        </x-ui.button>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button :href="route('client.invoices.pdf', $invoice)" variant="secondary" size="sm">
                {{ __('Download PDF') }}
            </x-ui.button>

            @if ($invoice->isPayable())
                <form method="POST" action="{{ route('client.invoices.pay', $invoice) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Pay now') }}
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

    @if ($errors->has('invoice'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('invoice') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('Invoice status')">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <span class="text-body-sm text-muted-foreground">{{ __('Current status') }}</span>
                    <x-ui.badge :variant="$statusVariant">{{ $invoice->status->label() }}</x-ui.badge>
                </div>

                <dl class="space-y-3 text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Issued') }}</dt>
                        <dd>{{ $invoice->issued_at?->timezone(config('app.timezone'))->format('Y-m-d') ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Due') }}</dt>
                        <dd>{{ $invoice->due_at?->timezone(config('app.timezone'))->format('Y-m-d') ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Amount due') }}</dt>
                        <dd class="tabular-nums font-medium">{{ number_format((float) $invoice->amountDue(), 2, '.', ' ') }} {{ $invoice->currency }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            @if ($pendingPayments->isNotEmpty())
                <x-ui.card :title="__('Pending payment')">
                    @foreach ($pendingPayments as $payment)
                        <div class="border-b border-border pb-4 last:border-0 last:pb-0">
                            <p class="text-body-sm font-medium">
                                {{ __('Payment reference: :reference', ['reference' => $payment->gateway_reference ?: '—']) }}
                            </p>
                            @if (filled($payment->notes))
                                <p class="mt-2 whitespace-pre-wrap text-body-sm text-muted-foreground">{{ $payment->notes }}</p>
                            @endif
                        </div>
                    @endforeach
                </x-ui.card>
            @endif

            <x-ui.card :title="__('Items')">
                <ul class="space-y-4">
                    @foreach ($invoice->items as $item)
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
                        <dd class="tabular-nums">{{ number_format((float) $invoice->subtotal, 2, '.', ' ') }} {{ $invoice->currency }}</dd>
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
    </div>
</x-layout.client>
