<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure API clients send/receive JSON.
 */
class ForceJsonResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if ($this->requiresJsonBody($request) && ! $this->hasJsonContentType($request)) {
            return response()->json([
                'error' => [
                    'code' => 'unsupported_media_type',
                    'message' => __('Content-Type must be application/json.'),
                ],
            ], 415);
        }

        $response = $next($request);

        if (! $response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }

    private function requiresJsonBody(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        return (int) $request->headers->get('Content-Length', 0) > 0
            || filled($request->getContent());
    }

    private function hasJsonContentType(Request $request): bool
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));

        return str_contains($contentType, 'application/json');
    }
}
