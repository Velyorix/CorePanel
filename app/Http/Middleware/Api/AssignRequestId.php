<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assign a correlation id for API requests.
 */
class AssignRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('corepanel.api.request_id_header', 'X-Request-Id');
        $incoming = trim((string) $request->headers->get($header, ''));
        $requestId = $incoming !== '' ? $incoming : (string) Str::uuid();

        $request->headers->set($header, $requestId);
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set($header, $requestId);

        return $response;
    }
}
