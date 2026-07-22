<?php

use Core\Themes\Services\ThemeViteEntryResolver;
use Illuminate\Contracts\Session\Session;

if (! function_exists('theme_vite_entries')) {
    /**
     * @return list<string>
     */
    function theme_vite_entries(?Session $session = null): array
    {
        return app(ThemeViteEntryResolver::class)->resolve($session);
    }
}
