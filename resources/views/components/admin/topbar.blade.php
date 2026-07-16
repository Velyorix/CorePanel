<div class="ml-auto flex items-center gap-3">
    <x-ui.theme-toggle />
    @auth
        <x-admin.notifications-dropdown />
        <x-ui.button
            :href="route('admin.profile.edit')"
            variant="ghost"
            size="sm"
            class="max-w-[12rem] truncate"
        >
            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
            <span class="sm:hidden">{{ __('Profile') }}</span>
        </x-ui.button>
    @endauth
</div>
