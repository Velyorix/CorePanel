@php
    $selectedPermissionIds = collect(old('permission_ids', isset($role) ? $role->permissions->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $selectedUserIds = collect(old('user_ids', isset($role) ? $role->users->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

<div class="space-y-6">
    <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $role->name ?? '') }}"
            @disabled(isset($role) && $role->is_system)
            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
            required
        >
        @if (isset($role) && $role->is_system)
            <input type="hidden" name="name" value="{{ $role->name }}">
            <p class="mt-1 text-xs text-zinc-500">System role names cannot be changed.</p>
        @endif
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-medium">Description</label>
        <input
            id="description"
            name="description"
            type="text"
            value="{{ old('description', $role->description ?? '') }}"
            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
        >
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="parent_id" class="block text-sm font-medium">Parent role</label>
        <select
            id="parent_id"
            name="parent_id"
            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
        >
            <option value="">None</option>
            @foreach ($parentRoles as $parentRole)
                <option value="{{ $parentRole->id }}" @selected((string) old('parent_id', $role->parent_id ?? '') === (string) $parentRole->id)>
                    {{ $parentRole->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <h2 class="text-sm font-medium">Permissions</h2>
        <div class="mt-3 space-y-4">
            @foreach ($permissions as $module => $modulePermissions)
                <fieldset class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $module }}</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($modulePermissions as $permission)
                            <label class="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="permission_ids[]"
                                    value="{{ $permission->id }}"
                                    @checked($selectedPermissionIds->contains($permission->id))
                                    class="mt-0.5"
                                >
                                <span>
                                    <span class="font-medium">{{ $permission->name }}</span>
                                    @if ($permission->description)
                                        <span class="block text-xs text-zinc-500">{{ $permission->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        @error('permission_ids')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <h2 class="text-sm font-medium">Assign users</h2>
        <div class="mt-3 max-h-64 space-y-2 overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            @forelse ($users as $user)
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="user_ids[]"
                        value="{{ $user->id }}"
                        @checked($selectedUserIds->contains($user->id))
                    >
                    <span>{{ $user->name }} &lt;{{ $user->email }}&gt;</span>
                </label>
            @empty
                <p class="text-sm text-zinc-500">No users available.</p>
            @endforelse
        </div>
        @error('user_ids')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
