<?php

namespace App\Http\Middleware;

use Closure;
use Core\Auth\Services\UserSessionTracker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAuthenticatedSession
{
    public function __construct(
        private readonly UserSessionTracker $userSessionTracker,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user !== null && $request->hasSession()) {
            $this->userSessionTracker->sync(
                $user,
                $request->session()->getId(),
                $request->ip(),
                $request->userAgent(),
            );
        }

        return $response;
    }
}
