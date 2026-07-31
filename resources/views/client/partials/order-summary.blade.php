@props([
    'summary',
    'showCheckoutCta' => false,
    'checkoutDisabled' => false,
])

<x-ui.card :title="__('Order summary')">
    <dl class="space-y-3 text-body-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">{{ __('Items') }}</dt>
            <dd>{{ $summary['item_count'] }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">{{ __('Recurring (excl. tax)') }}</dt>
            <dd>{{ $summary['recurring_subtotal'] }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">{{ __('Setup fees') }}</dt>
            <dd>{{ $summary['setup_subtotal'] }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">{{ __('First payment (excl. tax)') }}</dt>
            <dd>{{ $summary['first_payment_subtotal'] }}</dd>
        </div>
        @if (($summary['discount_amount'] ?? '0.00') !== '0.00')
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">
                    {{ __('Discount') }}
                    @if (! empty($summary['coupon_code']))
                        <span class="text-small">({{ $summary['coupon_code'] }})</span>
                    @endif
                </dt>
                <dd>-{{ $summary['discount_amount'] }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">{{ $summary['tax_label'] }}</dt>
            <dd>{{ $summary['first_payment_tax'] }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-t border-border pt-3 font-medium">
            <dt>{{ __('First payment total') }}</dt>
            <dd>{{ $summary['first_payment_total'] }}</dd>
        </div>
    </dl>

    @if ($summary['tax_is_estimate'])
        <p class="mt-3 text-small text-muted-foreground">
            {{ __('Tax is an estimate; final VAT is calculated at checkout.') }}
        </p>
    @endif

    @if ($showCheckoutCta)
        <div class="mt-6 flex flex-col gap-2">
            @if ($checkoutDisabled)
                <x-ui.button type="button" variant="primary" disabled>
                    {{ __('Proceed to checkout') }}
                </x-ui.button>
            @else
                <x-ui.button :href="route('client.checkout.index')" variant="primary">
                    {{ __('Proceed to checkout') }}
                </x-ui.button>
            @endif
            <x-ui.button :href="route('client.catalog.index')" variant="secondary">
                {{ __('Continue shopping') }}
            </x-ui.button>
        </div>
    @endif
</x-ui.card>
