<x-ui.card :title="__('Internal notes')">
    <p class="mb-4 text-body-sm text-muted-foreground">
        {{ __('Staff-only notes. Clients cannot see this section.') }}
    </p>

    @can('update', $client)
        <form method="POST" action="{{ route('admin.clients.notes.store', $client) }}" class="mb-6 space-y-4">
            @csrf

            <div class="w-full">
                <label for="note_body" class="mb-1.5 block text-body-sm font-medium text-foreground">
                    {{ __('Add note') }}
                </label>
                <textarea
                    id="note_body"
                    name="body"
                    rows="3"
                    required
                    maxlength="5000"
                    class="block w-full rounded-md border border-border bg-surface px-3 py-2 text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    placeholder="{{ __('Write an internal note…') }}"
                >{{ old('body') }}</textarea>
                @error('body')
                    <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary" size="sm">
                    {{ __('Save note') }}
                </x-ui.button>
            </div>
        </form>
    @endcan

    @if ($notes->isEmpty())
        <x-ui.empty
            :title="__('No internal notes')"
            :description="__('Add a note to keep staff context on this client.')"
        />
    @else
        <ul class="divide-y divide-border rounded-lg border border-border">
            @foreach ($notes as $note)
                <li class="px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-small text-muted-foreground">
                                <span class="font-medium text-foreground">{{ $note->author?->name ?: __('Unknown author') }}</span>
                                <span>·</span>
                                <span>{{ $note->created_at?->diffForHumans() }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-body-sm text-foreground">{{ $note->body }}</p>
                        </div>

                        @can('update', $client)
                            <form
                                method="POST"
                                action="{{ route('admin.clients.notes.destroy', [$client, $note]) }}"
                                onsubmit="return confirm(@js(__('Delete this note?')))"
                            >
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="danger" size="sm">
                                    {{ __('Delete') }}
                                </x-ui.button>
                            </form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
