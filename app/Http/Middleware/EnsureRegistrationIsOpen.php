<?php

namespace App\Http\Middleware;

use Closure;
use Core\Auth\Services\RegistrationGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsOpen
{
    public function __construct(
        private readonly RegistrationGate $registrationGate,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->registrationGate->isOpen()) {
            abort(404);
        }

        return $next($request);
    }
}
