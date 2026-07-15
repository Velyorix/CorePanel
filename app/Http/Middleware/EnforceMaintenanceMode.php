<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMaintenanceMode
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('corepanel.maintenance.enabled', false)) {
            return $next($request);
        }

        if ($this->isExcepted($request)) {
            return $next($request);
        }

        $message = (string) config('corepanel.maintenance.message');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => [
                    'code' => 'maintenance_mode',
                    'message' => $message,
                ],
            ], 503);
        }

        return response($message, 503);
    }

    private function isExcepted(Request $request): bool
    {
        $patterns = config('corepanel.maintenance.except', ['up']);

        foreach ($patterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
