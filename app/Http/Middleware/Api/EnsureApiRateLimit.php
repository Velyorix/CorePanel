<?php

namespace App\Http\Middleware\Api;

use Closure;
use Core\API\Services\ApiRateLimiter;
use Core\API\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global API rate limiting with X-RateLimit-* and Retry-After headers.
 */
class EnsureApiRateLimit
{
    public function __construct(
        private readonly ApiRateLimiter $limiter,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->limiter->enabled()) {
            return $next($request);
        }

        $limit = $this->limiter->maxAttempts();

        if ($this->limiter->tooManyAttempts($request)) {
            $retryAfter = $this->limiter->retryAfter($request);

            $response = ApiResponse::error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again later.'),
                429,
                ['retry_after' => $retryAfter],
            );

            return $this->withHeaders($response, $limit, 0, $retryAfter, limited: true);
        }

        $this->limiter->hit($request);

        $response = $next($request);
        $remaining = $this->limiter->remaining($request);

        return $this->withHeaders(
            $response,
            $limit,
            $remaining,
            $this->limiter->retryAfter($request),
            limited: false,
        );
    }

    private function withHeaders(
        Response $response,
        int $limit,
        int $remaining,
        int $retryAfter,
        bool $limited = false,
    ): Response {
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set(
            'X-RateLimit-Reset',
            (string) (now()->getTimestamp() + max(0, $retryAfter)),
        );

        if ($limited) {
            $response->headers->set('Retry-After', (string) max(1, $retryAfter));
        }

        return $response;
    }
}
