<x-layout.client
    :title="__('Open ticket')"
    :page-heading="__('Open a support ticket')"
>
    <x-slot:subtitle>
        {{ __('Describe your issue and our team will get back to you.') }}
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
            ['label' => __('Tickets'), 'url' => route('client.tickets.index')],
            ['label' => __('Open ticket')],
        ]" />
    </x-slot:breadcrumbs>

    @if ($errors->has('ticket'))
        <div class="mb-6">
            <x-ui.alert variant="danger">{{ $errors->first('ticket') }}</x-ui.alert>
        </div>
    @endif

    @if ($clientMissing)
        <x-ui.empty
            :title="__('No client account yet')"
            :description="__('You need a linked client account before opening a ticket.')"
        />
    @else
        <x-ui.card :title="__('New ticket')">
            <form
                method="POST"
                action="{{ route('client.tickets.store') }}"
                enctype="multipart/form-data"
                class="space-y-4"
            >
                @csrf

                <x-ui.input
                    name="subject"
                    :label="__('Subject')"
                    :value="old('subject')"
                    :error="$errors->first('subject')"
                    required
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="category_id" :label="__('Category')" :error="$errors->first('category_id')">
                        <option value="">{{ __('No category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="priority" :label="__('Priority')" :error="$errors->first('priority')">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', \Core\Tickets\Enums\TicketPriority::Normal->value) === $priority->value)>
                                {{ $priority->label() }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select name="service_id" :label="__('Related service')" :error="$errors->first('service_id')">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((string) old('service_id', request('service_id')) === (string) $service->id)>
                                {{ $service->hostname ?: __('Service #:id', ['id' => $service->id]) }}
                                @if ($service->product?->name)
                                    — {{ $service->product->name }}
                                @endif
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="order_id" :label="__('Related order')" :error="$errors->first('order_id')">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}" @selected((string) old('order_id', request('order_id')) === (string) $order->id)>
                                {{ $order->order_number ?: __('Order #:id', ['id' => $order->id]) }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="invoice_id" :label="__('Related invoice')" :error="$errors->first('invoice_id')">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected((string) old('invoice_id', request('invoice_id')) === (string) $invoice->id)>
                                {{ $invoice->invoice_number ?: __('Invoice #:id', ['id' => $invoice->id]) }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div>
                    <label for="message" class="mb-1.5 block text-body-sm font-medium text-foreground">
                        {{ __('Message') }}
                    </label>
                    <textarea
                        id="message"
                        name="message"
                        rows="6"
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

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit" variant="primary" size="sm">
                        {{ __('Submit ticket') }}
                    </x-ui.button>
                    <x-ui.button :href="route('client.tickets.index')" variant="ghost" size="sm">
                        {{ __('Cancel') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif
</x-layout.client>
