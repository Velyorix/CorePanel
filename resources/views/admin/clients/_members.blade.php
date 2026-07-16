<x-ui.card :title="__('Associated users')">
    @can('update', $client)
        <form method="POST" action="{{ route('admin.clients.members.store', $client) }}" class="mb-6 grid gap-4 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
            @csrf

            <x-ui.select name="user_id" :label="__('User')" required>
                <option value="">{{ __('Select a user') }}</option>
                @forelse ($availableUsers as $user)
                    <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>
                        {{ $user->name }} ({{ $user->email }})
                    </option>
                @empty
                    <option value="" disabled>{{ __('No available users') }}</option>
                @endforelse
            </x-ui.select>

            <x-ui.select name="role" :label="__('Role')" required>
                @foreach ($membershipRoles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', \Core\Clients\Enums\ClientMembershipRole::User->value) === $role->value)>
                        {{ $role->label() }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.button type="submit" variant="primary" :disabled="$availableUsers->isEmpty()">
                {{ __('Add member') }}
            </x-ui.button>
        </form>
    @endcan

    @if ($client->memberships->isEmpty())
        <x-ui.empty
            :title="__('No members yet')"
            :description="__('Attach existing users to this client account.')"
        />
    @else
        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="min-w-full divide-y divide-border text-start text-body-sm">
                <thead class="bg-muted/60 text-small font-medium uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">{{ __('User') }}</th>
                        <th class="px-4 py-3">{{ __('Role') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($client->memberships as $membership)
                        @php
                            $isPrimaryOwner = $client->user_id !== null && $client->user_id === $membership->user_id;
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $membership->user?->name ?: __('Unknown user') }}</div>
                                <div class="text-small text-muted-foreground">{{ $membership->user?->email }}</div>
                                @if ($isPrimaryOwner)
                                    <div class="mt-1">
                                        <x-ui.badge variant="primary">{{ __('Primary owner') }}</x-ui.badge>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @can('update', $client)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.clients.members.update', [$client, $membership]) }}"
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        @csrf
                                        @method('PUT')

                                        <x-ui.select name="role" class="min-w-[10rem]">
                                            @foreach ($membershipRoles as $role)
                                                <option value="{{ $role->value }}" @selected($membership->role->value === $role->value)>
                                                    {{ $role->label() }}
                                                </option>
                                            @endforeach
                                        </x-ui.select>

                                        <x-ui.button type="submit" variant="secondary" size="sm">
                                            {{ __('Update') }}
                                        </x-ui.button>
                                    </form>
                                @else
                                    <x-ui.badge variant="neutral">{{ $membership->role->label() }}</x-ui.badge>
                                @endcan
                            </td>
                            <td class="px-4 py-3 text-end">
                                @can('update', $client)
                                    @if ($isPrimaryOwner)
                                        <span class="text-small text-muted-foreground">{{ __('Reassign ownership to remove') }}</span>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.clients.members.destroy', [$client, $membership]) }}"
                                            onsubmit="return confirm(@js(__('Remove this member?')))"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="danger" size="sm">
                                                {{ __('Remove') }}
                                            </x-ui.button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>
