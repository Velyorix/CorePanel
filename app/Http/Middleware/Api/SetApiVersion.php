<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose the active API version on every v1 response.
 */
class SetApiVersion
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $header = (string) config('corepanel.api.version_header', 'X-Api-Version');
        $version = (string) config('corepanel.api.version', 'v1');

        $response->headers->set($header, $version);
        $request->attributes->set('api_version', $version);

        return $response;
    }
}
