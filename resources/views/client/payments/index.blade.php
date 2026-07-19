<x-layout.client
    :title="__('Payments')"
    :page-heading="__('Your payments')"
>
    <x-slot:subtitle>
        {{ __('Track your payment history.') }}
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
            ['label' => __('Payments')],
        ]" />
    </x-slot:breadcrumbs>

    @if ($clientMissing)
        <x-ui.empty
            :title="__('No client account yet')"
            :description="__('Payments will appear here once your account is linked to a client.')"
        />
    @else
        <x-ui.table :paginator="$payments">
            <x-slot:filters>
                <form method="GET" action="{{ route('client.payments.index') }}" class="flex w-full flex-wrap items-end gap-3">
                    <div class="min-w-40">
                        <x-ui.select name="status" :label="__('Status')">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(($filters['status']?->value ?? null) === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    @if (filled(request('sort')))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if (filled(request('dir')))
                        <input type="hidden" name="dir" value="{{ request('dir') }}">
                    @endif

                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Apply') }}
                    </x-ui.button>

                    @if ($filters['status'] !== null)
                        <x-ui.button :href="route('client.payments.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                            {{ __('Clear') }}
                        </x-ui.button>
                    @endif
                </form>
            </x-slot:filters>

            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Invoice') }}</th>
                    <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="method">{{ __('Method') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="amount">{{ __('Amount') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="created_at">{{ __('Date') }}</x-ui.table-heading>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </x-slot:head>

            <x-slot:empty>
                <tr>
                    <td colspan="6" class="p-4">
                        <x-ui.empty
                            :title="__('No payments yet')"
                            :description="__('Your payment history will appear here.')"
                        />
                    </td>
                </tr>
            </x-slot:empty>

            @foreach ($payments as $payment)
                @php
                    $paymentVariant = match ($payment->status) {
                        \Core\Billing\Enums\PaymentStatus::Completed => 'success',
                        \Core\Billing\Enums\PaymentStatus::Pending => 'warning',
                        \Core\Billing\Enums\PaymentStatus::Failed => 'danger',
                        default => 'neutral',
                    };
                @endphp
                <tr class="hover:bg-muted/40">
                    <td class="px-4 py-3">
                        @if ($payment->invoice)
                            {{ $payment->invoice->invoice_number ?: '#'.$payment->invoice->id }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$paymentVariant">
                            {{ $payment->status->label() }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">{{ $payment->method }}</td>
                    <td class="px-4 py-3 tabular-nums">
                        {{ number_format((float) $payment->amount, 2, '.', ' ') }}
                        <span class="text-muted-foreground">{{ $payment->currency }}</span>
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                        {{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </td>
                    <td class="px-4 py-3 text-end">
                        <x-ui.button :href="route('client.payments.show', $payment)" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.client>
