<?php

namespace App\Http\Middleware;

use Closure;
use Core\Auth\Services\EmailVerificationGate;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function __construct(
        private readonly EmailVerificationGate $emailVerificationGate,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (! $this->emailVerificationGate->isRequired()) {
            return $next($request);
        }

        $user = $request->user();

        if (
            ! $user
            || ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())
        ) {
            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : Redirect::guest(route($redirectToRoute ?: 'verification.notice'));
        }

        return $next($request);
    }
}
