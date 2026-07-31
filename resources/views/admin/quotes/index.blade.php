<x-layout.admin
    :title="__('Quotes')"
    :page-heading="__('Quotes')"
>
    <x-slot:subtitle>
        {{ __('Prepare, send, and convert client quotes.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Quotes')],
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

    <x-ui.table :paginator="$quotes">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.quotes.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Quote #, contact, client…')"
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
                    <x-ui.button :href="route('admin.quotes.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="quote_number">{{ __('Quote') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Client') }}</th>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <x-ui.table-heading sort="total_amount">{{ __('Total') }}</x-ui.table-heading>
                <x-ui.table-heading sort="valid_until">{{ __('Valid until') }}</x-ui.table-heading>
                <x-ui.table-heading sort="created_at">{{ __('Created') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="7" class="p-4">
                    <x-ui.empty
                        :title="__('No quotes found')"
                        :description="filled($filters['q']) || $filters['status'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Quotes will appear here once created.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($quotes as $quote)
            @php
                $statusVariant = match ($quote->status) {
                    \Core\Billing\Enums\QuoteStatus::Accepted, \Core\Billing\Enums\QuoteStatus::Converted => 'success',
                    \Core\Billing\Enums\QuoteStatus::Sent => 'primary',
                    \Core\Billing\Enums\QuoteStatus::Declined, \Core\Billing\Enums\QuoteStatus::Expired, \Core\Billing\Enums\QuoteStatus::Cancelled => 'danger',
                    default => 'neutral',
                };
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $quote->quote_number ?: __('Draft #'.$quote->id) }}</div>
                    <div class="text-small text-muted-foreground">#{{ $quote->id }}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $quote->client?->company_name ?: __('Client #'.($quote->client_id ?? '—')) }}</div>
                    <div class="text-small text-muted-foreground">{{ $quote->contact_email }}</div>
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$statusVariant">
                        {{ $quote->status->label() }}
                    </x-ui.badge>
                </td>
                <td class="px-4 py-3 tabular-nums">
                    {{ number_format((float) $quote->total_amount, 2, '.', ' ') }}
                    <span class="text-muted-foreground">{{ $quote->currency }}</span>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ $quote->valid_until?->timezone(config('app.timezone'))->format('Y-m-d') ?: '—' }}
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ $quote->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.quotes.show', $quote)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
