<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supported = config('corepanel.locale.supported', [config('app.locale')]);
        $candidates = [];

        if ($request->hasSession()) {
            $candidates[] = $request->session()->get('locale');
        }

        $candidates[] = $request->header('X-Locale');
        $candidates[] = config('app.locale');

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        $fallback = config('app.fallback_locale', config('app.locale'));

        return in_array($fallback, $supported, true) ? $fallback : $supported[0];
    }
}
