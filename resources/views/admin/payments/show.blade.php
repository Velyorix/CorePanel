@php
    $paymentVariant = match ($payment->status) {
        \Core\Billing\Enums\PaymentStatus::Completed => 'success',
        \Core\Billing\Enums\PaymentStatus::Pending => 'warning',
        \Core\Billing\Enums\PaymentStatus::Failed => 'danger',
        \Core\Billing\Enums\PaymentStatus::Refunded => 'neutral',
        default => 'neutral',
    };

    $heading = __('Payment #:id', ['id' => $payment->id]);
@endphp

<x-layout.admin
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Payment attempt details and manual confirmation.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Payments'), 'url' => route('admin.payments.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('manage', $payment)
            @if ($payment->status === \Core\Billing\Enums\PaymentStatus::Pending)
                <form method="POST" action="{{ route('admin.payments.complete', $payment) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Mark completed') }}
                    </x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.payments.fail', $payment) }}">
                    @csrf
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Mark failed') }}
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

    @if ($errors->has('payment'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('payment') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Overview')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                    <dd>
                        <x-ui.badge :variant="$paymentVariant">{{ $payment->status->label() }}</x-ui.badge>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Method') }}</dt>
                    <dd>{{ $payment->method }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Amount') }}</dt>
                    <dd class="tabular-nums font-medium">{{ number_format((float) $payment->amount, 2, '.', ' ') }} {{ $payment->currency }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Transaction ID') }}</dt>
                    <dd>{{ $payment->transaction_id ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Gateway reference') }}</dt>
                    <dd>{{ $payment->gateway_reference ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Paid') }}</dt>
                    <dd>{{ $payment->paid_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Created') }}</dt>
                    <dd>{{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                @if (filled($payment->notes))
                    <div class="border-t border-border pt-3">
                        <dt class="mb-1 text-muted-foreground">{{ __('Notes') }}</dt>
                        <dd class="whitespace-pre-wrap">{{ $payment->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Invoice & client')">
            <dl class="space-y-3 text-body-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Invoice') }}</dt>
                    <dd>
                        @if ($payment->invoice)
                            <a href="{{ route('admin.invoices.show', $payment->invoice) }}" class="text-primary hover:underline">
                                {{ $payment->invoice->invoice_number ?: '#'.$payment->invoice->id }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Client') }}</dt>
                    <dd>
                        @if ($payment->client)
                            <a href="{{ route('admin.clients.show', $payment->client) }}" class="text-primary hover:underline">
                                {{ $payment->client->company_name ?: __('Client #'.$payment->client->id) }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layout.admin>
