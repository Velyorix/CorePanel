<?php

namespace App\Http\Middleware;

use Closure;
use Core\Auth\Services\LoginRateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginIsNotRateLimited
{
    public function __construct(
        private readonly LoginRateLimiter $loginRateLimiter,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = (string) $request->input('email', '');

        if ($email === '') {
            return $next($request);
        }

        if (! $this->loginRateLimiter->tooManyAttempts($email, $request->ip())) {
            return $next($request);
        }

        $seconds = $this->loginRateLimiter->availableIn($email, $request->ip());

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ]);
    }
}
