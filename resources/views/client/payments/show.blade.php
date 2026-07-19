@php
    $paymentVariant = match ($payment->status) {
        \Core\Billing\Enums\PaymentStatus::Completed => 'success',
        \Core\Billing\Enums\PaymentStatus::Pending => 'warning',
        \Core\Billing\Enums\PaymentStatus::Failed => 'danger',
        default => 'neutral',
    };

    $heading = __('Payment #:id', ['id' => $payment->id]);
@endphp

<x-layout.client
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ __('Payment attempt details.') }}
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
            ['label' => __('Payments'), 'url' => route('client.payments.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6">
        <x-ui.button :href="route('client.payments.index')" variant="secondary" size="sm">
            {{ __('Back to payments') }}
        </x-ui.button>
    </div>

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
                <dt class="text-muted-foreground">{{ __('Reference') }}</dt>
                <dd>{{ $payment->gateway_reference ?: '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">{{ __('Paid') }}</dt>
                <dd>{{ $payment->paid_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: '—' }}</dd>
            </div>
            @if ($payment->invoice)
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">{{ __('Invoice') }}</dt>
                    <dd>
                        <a href="{{ route('client.invoices.show', $payment->invoice) }}" class="text-primary hover:underline">
                            {{ $payment->invoice->invoice_number ?: '#'.$payment->invoice->id }}
                        </a>
                    </dd>
                </div>
            @endif
            @if (filled($payment->notes))
                <div class="border-t border-border pt-3">
                    <dt class="mb-1 text-muted-foreground">{{ __('Notes') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $payment->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>
</x-layout.client>
