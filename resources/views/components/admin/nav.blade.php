@php
    use Core\Admin\Navigation\AdminNavigation;
    use Core\Auth\Models\User;

    $user = auth()->user();
    $sections = $user instanceof User
        ? app(AdminNavigation::class)->forUser($user)
        : [];
@endphp

<nav class="space-y-6" aria-label="{{ __('Admin menu') }}">
    @foreach ($sections as $section)
        <div class="space-y-1">
            @if (filled($section['label']))
                <p class="px-3 pb-1 text-small font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ $section['label'] }}
                </p>
            @endif

            <ul class="space-y-0.5">
                @foreach ($section['items'] as $item)
                    <li>
                        @include('components.admin.partials.nav-item', ['item' => $item, 'depth' => 0])
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
