<?php

namespace Core\Themes\Http\Middleware;

use Closure;
use Core\Themes\Services\ThemeManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyEffectiveTheme
{
    public function __construct(
        private readonly ThemeManager $themes,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('corepanel.themes.auto_load_active', true)) {
            $session = $request->hasSession() ? $request->session() : null;
            $this->themes->applyEffective($session);
        }

        return $next($request);
    }
}
