@php
    $clientStatuses = $statuses;
@endphp

<x-layout.client
    :title="__('Invoices')"
    :page-heading="__('Your invoices')"
>
    <x-slot:subtitle>
        {{ __('Review invoices, download PDFs, and pay what is due.') }}
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
            ['label' => __('Invoices')],
        ]" />
    </x-slot:breadcrumbs>

    @if (session('status'))
        <div class="mb-6">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    @if ($clientMissing)
        <x-ui.empty
            :title="__('No client account yet')"
            :description="__('Invoices will appear here once your account is linked to a client.')"
        />
    @else
        <x-ui.table :paginator="$invoices">
            <x-slot:filters>
                <form method="GET" action="{{ route('client.invoices.index') }}" class="flex w-full flex-wrap items-end gap-3">
                    <div class="min-w-40">
                        <x-ui.select name="status" :label="__('Status')">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($clientStatuses as $status)
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
                        <x-ui.button :href="route('client.invoices.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                            {{ __('Clear') }}
                        </x-ui.button>
                    @endif
                </form>
            </x-slot:filters>

            <x-slot:head>
                <tr>
                    <x-ui.table-heading sort="invoice_number">{{ __('Invoice') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="total_amount">{{ __('Total') }}</x-ui.table-heading>
                    <x-ui.table-heading sort="due_at">{{ __('Due') }}</x-ui.table-heading>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </x-slot:head>

            <x-slot:empty>
                <tr>
                    <td colspan="5" class="p-4">
                        <x-ui.empty
                            :title="__('No invoices yet')"
                            :description="$filters['status'] !== null
                                ? __('Try another status filter.')
                                : __('Invoices will appear here once issued.')"
                        />
                    </td>
                </tr>
            </x-slot:empty>

            @foreach ($invoices as $invoice)
                @php
                    $statusVariant = match ($invoice->status) {
                        \Core\Billing\Enums\InvoiceStatus::Paid => 'success',
                        \Core\Billing\Enums\InvoiceStatus::Unpaid => 'primary',
                        \Core\Billing\Enums\InvoiceStatus::Overdue => 'danger',
                        default => 'neutral',
                    };
                @endphp
                <tr class="hover:bg-muted/40">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $invoice->invoice_number ?: __('Invoice #:id', ['id' => $invoice->id]) }}</div>
                        <div class="text-small text-muted-foreground">#{{ $invoice->id }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :variant="$statusVariant">
                            {{ $invoice->status->label() }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 py-3 tabular-nums">
                        {{ number_format((float) $invoice->total_amount, 2, '.', ' ') }}
                        <span class="text-muted-foreground">{{ $invoice->currency }}</span>
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                        {{ $invoice->due_at?->timezone(config('app.timezone'))->format('Y-m-d') ?: '—' }}
                    </td>
                    <td class="px-4 py-3 text-end">
                        <x-ui.button :href="route('client.invoices.show', $invoice)" variant="ghost" size="sm">
                            {{ __('View') }}
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.client>
