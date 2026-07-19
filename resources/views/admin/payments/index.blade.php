<x-layout.admin
    :title="__('Payments')"
    :page-heading="__('Payments')"
>
    <x-slot:subtitle>
        {{ __('Track payment attempts and confirm manual transfers.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Payments')],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6">
            <x-ui.alert variant="danger" :title="__('Unable to continue')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </div>
    @endif

    <x-ui.table :paginator="$payments">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.payments.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Reference, invoice, client…')"
                    />
                </div>

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

                @if (filled($filters['q']) || $filters['status'] !== null)
                    <x-ui.button :href="route('admin.payments.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Payment') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Invoice') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Client') }}</th>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <x-ui.table-heading sort="method">{{ __('Method') }}</x-ui.table-heading>
                <x-ui.table-heading sort="amount">{{ __('Amount') }}</x-ui.table-heading>
                <x-ui.table-heading sort="created_at">{{ __('Created') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="8" class="p-4">
                    <x-ui.empty
                        :title="__('No payments found')"
                        :description="filled($filters['q']) || $filters['status'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Payments will appear here once invoices are paid.')"
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
                    \Core\Billing\Enums\PaymentStatus::Refunded => 'neutral',
                    default => 'neutral',
                };
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">#{{ $payment->id }}</div>
                    <div class="text-small text-muted-foreground">{{ $payment->gateway_reference ?: '—' }}</div>
                </td>
                <td class="px-4 py-3">
                    @if ($payment->invoice)
                        <a href="{{ route('admin.invoices.show', $payment->invoice) }}" class="text-primary hover:underline">
                            {{ $payment->invoice->invoice_number ?: '#'.$payment->invoice->id }}
                        </a>
                    @else
                        —
                    @endif
                </td>
                <td class="px-4 py-3">
                    {{ $payment->client?->company_name ?: __('Client #'.($payment->client_id ?? '—')) }}
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
                    <x-ui.button :href="route('admin.payments.show', $payment)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
