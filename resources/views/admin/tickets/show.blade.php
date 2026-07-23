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

    $heading = $ticket->ticket_number ?: __('Ticket #:id', ['id' => $ticket->id]);
@endphp

<x-layout.admin
    :title="$heading"
    :page-heading="$heading"
>
    <x-slot:subtitle>
        {{ $ticket->subject }}
    </x-slot:subtitle>

    <x-slot:topbar>
        <x-admin.topbar />
    </x-slot:topbar>

    <x-slot:breadcrumbs>
        <x-ui.breadcrumb :items="[
            ['label' => __('Admin'), 'url' => route('admin.dashboard')],
            ['label' => __('Tickets'), 'url' => route('admin.tickets.index')],
            ['label' => $heading],
        ]" />
    </x-slot:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        @can('close', $ticket)
            @if ($ticket->status->isClosed())
                <form method="POST" action="{{ route('admin.tickets.reopen', $ticket) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        {{ __('Reopen') }}
                    </x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.tickets.close', $ticket) }}" onsubmit="return confirm(@js(__('Close this ticket?')))">
                    @csrf
                    <x-ui.button type="submit" variant="danger" size="sm">
                        {{ __('Close') }}
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

    @if ($errors->has('ticket'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('ticket') }}</x-ui.alert>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <x-ui.card :title="__('Overview')">
                <dl class="space-y-3 text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Status') }}</dt>
                        <dd>
                            <x-ui.badge :variant="$statusVariant">{{ $ticket->status->label() }}</x-ui.badge>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Priority') }}</dt>
                        <dd>
                            <x-ui.badge :variant="$priorityVariant">{{ $ticket->priority->label() }}</x-ui.badge>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Assignee') }}</dt>
                        <dd>{{ $ticket->assignee?->name ?: __('Unassigned') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Category') }}</dt>
                        <dd>{{ $ticket->category?->name ?: '—' }}</dd>
                    </div>
                    @if ($ticket->service)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Service') }}</dt>
                            <dd class="text-end">
                                <a href="{{ route('admin.services.show', $ticket->service) }}" class="text-primary hover:underline">
                                    {{ $ticket->service->hostname ?: __('Service #:id', ['id' => $ticket->service->id]) }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if ($ticket->order)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Order') }}</dt>
                            <dd class="text-end">
                                <a href="{{ route('admin.orders.show', $ticket->order) }}" class="text-primary hover:underline">
                                    {{ $ticket->order->order_number ?: __('Order #:id', ['id' => $ticket->order->id]) }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if ($ticket->invoice)
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">{{ __('Invoice') }}</dt>
                            <dd class="text-end">
                                <a href="{{ route('admin.invoices.show', $ticket->invoice) }}" class="text-primary hover:underline">
                                    {{ $ticket->invoice->invoice_number ?: __('Invoice #:id', ['id' => $ticket->invoice->id]) }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Client') }}</dt>
                        <dd class="text-end">
                            @if ($ticket->client)
                                <a href="{{ route('admin.clients.show', $ticket->client) }}" class="text-primary hover:underline">
                                    {{ $ticket->client->company_name ?: __('Client #'.$ticket->client->id) }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Created') }}</dt>
                        <dd>{{ $ticket->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('Updated') }}</dt>
                        <dd>{{ $ticket->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            @can('assign', $ticket)
                <x-ui.card :title="__('Assignment')">
                    <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="space-y-3">
                        @csrf
                        <x-ui.select name="assigned_to" :label="__('Assignee')" :error="$errors->first('assigned_to')">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}" @selected($ticket->assigned_to === $assignee->id)>
                                    {{ $assignee->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Save assignment') }}
                        </x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('Priority')">
                    <form method="POST" action="{{ route('admin.tickets.priority', $ticket) }}" class="space-y-3">
                        @csrf
                        <x-ui.select name="priority" :label="__('Priority')" :error="$errors->first('priority')">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>
                                    {{ $priority->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Update priority') }}
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @endcan
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('Conversation')">
                <div class="space-y-4">
                    @forelse ($ticket->messages as $message)
                        <div class="rounded-md border border-border bg-surface p-4">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-small text-muted-foreground">
                                <span class="font-medium text-foreground">
                                    {{ $message->author?->name ?: __('Unknown user') }}
                                </span>
                                <span>
                                    {{ $message->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </span>
                            </div>
                            <div class="whitespace-pre-wrap text-body-sm text-foreground">{{ $message->message }}</div>

                            @if (is_array($message->attachments) && $message->attachments !== [])
                                <ul class="mt-3 space-y-1 border-t border-border pt-3 text-body-sm">
                                    @foreach ($message->attachments as $attachment)
                                        @if (is_array($attachment) && isset($attachment['id'], $attachment['original_name']))
                                            <li>
                                                <a
                                                    href="{{ route('admin.tickets.attachments.download', [$ticket, $attachment['id']]) }}"
                                                    class="text-primary hover:underline"
                                                >
                                                    {{ $attachment['original_name'] }}
                                                </a>
                                                @if (isset($attachment['size']))
                                                    <span class="text-muted-foreground">
                                                        ({{ number_format(((int) $attachment['size']) / 1024, 1) }} KB)
                                                    </span>
                                                @endif
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty
                            :title="__('No messages yet')"
                            :description="__('Replies will appear in this thread.')"
                        />
                    @endforelse
                </div>
            </x-ui.card>

            @can('reply', $ticket)
                @if (! $ticket->status->isClosed())
                    <x-ui.card :title="__('Reply')">
                        <form
                            method="POST"
                            action="{{ route('admin.tickets.reply', $ticket) }}"
                            enctype="multipart/form-data"
                            class="space-y-4"
                        >
                            @csrf
                            <div>
                                <label for="message" class="mb-1.5 block text-body-sm font-medium text-foreground">
                                    {{ __('Message') }}
                                </label>
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="5"
                                    class="block w-full rounded-md border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring @error('message') border-danger @enderror"
                                    required
                                >{{ old('message') }}</textarea>
                                @error('message')
                                    <p class="mt-1 text-small text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="files" class="mb-1.5 block text-body-sm font-medium text-foreground">
                                    {{ __('Attachments') }}
                                </label>
                                <input
                                    id="files"
                                    type="file"
                                    name="files[]"
                                    multiple
                                    class="block w-full text-body-sm text-muted-foreground file:me-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-body-sm file:font-medium file:text-foreground"
                                >
                                <p class="mt-1 text-small text-muted-foreground">
                                    {{ __('Optional. Allowed types are restricted for security.') }}
                                </p>
                                @error('files')
                                    <p class="mt-1 text-small text-danger">{{ $message }}</p>
                                @enderror
                                @error('files.*')
                                    <p class="mt-1 text-small text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <x-ui.button type="submit" variant="primary" size="sm">
                                {{ __('Send reply') }}
                            </x-ui.button>
                        </form>
                    </x-ui.card>
                @endif
            @endcan
        </div>
    </div>
</x-layout.admin>
