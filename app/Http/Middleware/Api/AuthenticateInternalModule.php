<?php

namespace App\Http\Middleware\Api;

use Closure;
use Core\API\Services\InternalApiAuthenticator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate module callers on /api/internal/* via token + HMAC.
 */
class AuthenticateInternalModule
{
    public function __construct(
        private readonly InternalApiAuthenticator $authenticator,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->authenticator->authenticate($request);

        return $next($request);
    }
}
