<x-layout.admin
    :title="__('Tickets')"
    :page-heading="__('Tickets')"
>
    <x-slot:subtitle>
        {{ __('Support inbox — review, assign, and respond to client tickets.') }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Tickets')],
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

    <x-ui.table :paginator="$tickets">
        <x-slot:filters>
            <form method="GET" action="{{ route('admin.tickets.index') }}" class="flex w-full flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <x-ui.input
                        name="q"
                        :label="__('Search')"
                        :value="$filters['q']"
                        :placeholder="__('Ticket #, subject, client…')"
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

                <div class="min-w-40">
                    <x-ui.select name="priority" :label="__('Priority')">
                        <option value="">{{ __('All priorities') }}</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority']?->value ?? null) === $priority->value)>
                                {{ $priority->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="min-w-44">
                    <x-ui.select name="assigned_to" :label="__('Assignee')">
                        <option value="">{{ __('All assignees') }}</option>
                        <option value="unassigned" @selected($filters['assigned_to'] === 'unassigned')>
                            {{ __('Unassigned') }}
                        </option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected($filters['assigned_to'] === $assignee->id)>
                                {{ $assignee->name }}
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

                @if (filled($filters['q']) || $filters['status'] !== null || $filters['priority'] !== null || $filters['assigned_to'] !== null)
                    <x-ui.button :href="route('admin.tickets.index', request()->only(['sort', 'dir']))" variant="ghost" size="sm">
                        {{ __('Clear') }}
                    </x-ui.button>
                @endif
            </form>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <x-ui.table-heading sort="ticket_number">{{ __('Ticket') }}</x-ui.table-heading>
                <x-ui.table-heading sort="subject">{{ __('Subject') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Client') }}</th>
                <x-ui.table-heading sort="status">{{ __('Status') }}</x-ui.table-heading>
                <x-ui.table-heading sort="priority">{{ __('Priority') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium">{{ __('Assignee') }}</th>
                <x-ui.table-heading sort="created_at">{{ __('Created') }}</x-ui.table-heading>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </x-slot:head>

        <x-slot:empty>
            <tr>
                <td colspan="8" class="p-4">
                    <x-ui.empty
                        :title="__('No tickets found')"
                        :description="filled($filters['q']) || $filters['status'] !== null || $filters['priority'] !== null || $filters['assigned_to'] !== null
                            ? __('Try adjusting your search or filters.')
                            : __('Tickets will appear here once clients open support requests.')"
                    />
                </td>
            </tr>
        </x-slot:empty>

        @foreach ($tickets as $ticket)
            @php
                $statusVariant = match ($ticket->status) {
                    \Core\Tickets\Enums\TicketStatus::Open => 'primary',
                    \Core\Tickets\Enums\TicketStatus::InProgress => 'warning',
                    \Core\Tickets\Enums\TicketStatus::Answered => 'success',
                    \Core\Tickets\Enums\TicketStatus::Pending => 'neutral',
                    \Core\Tickets\Enums\TicketStatus::Closed => 'neutral',
                };

                $priorityVariant = match ($ticket->priority) {
                    \Core\Tickets\Enums\TicketPriority::Low => 'neutral',
                    \Core\Tickets\Enums\TicketPriority::Normal => 'primary',
                    \Core\Tickets\Enums\TicketPriority::High => 'warning',
                    \Core\Tickets\Enums\TicketPriority::Urgent => 'danger',
                };
            @endphp
            <tr class="hover:bg-muted/40">
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $ticket->ticket_number ?: '#'.$ticket->id }}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $ticket->subject }}</div>
                    @if ($ticket->category)
                        <div class="text-small text-muted-foreground">{{ $ticket->category->name }}</div>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $ticket->client?->company_name ?: __('Client #'.($ticket->client_id ?? '—')) }}</div>
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$statusVariant">{{ $ticket->status->label() }}</x-ui.badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.badge :variant="$priorityVariant">{{ $ticket->priority->label() }}</x-ui.badge>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ $ticket->assignee?->name ?: __('Unassigned') }}
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ $ticket->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                </td>
                <td class="px-4 py-3 text-end">
                    <x-ui.button :href="route('admin.tickets.show', $ticket)" variant="ghost" size="sm">
                        {{ __('View') }}
                    </x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layout.admin>
