@php
    $storageKey = config('corepanel.ui.theme.storage_key', 'corepanel.theme');
    $defaultTheme = config('corepanel.ui.theme.default', 'system');
@endphp
{{-- Apply theme before paint to avoid a light/dark flash (FOUC). --}}
<script>
    (function () {
        try {
            var key = @js($storageKey);
            var fallback = @js($defaultTheme);
            var stored = localStorage.getItem(key);
            var preference = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : fallback;
            var dark =
                preference === 'dark' ||
                (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.dataset.theme = preference;
            document.documentElement.dataset.themeResolved = dark ? 'dark' : 'light';
        } catch (e) {}
    })();
</script>
