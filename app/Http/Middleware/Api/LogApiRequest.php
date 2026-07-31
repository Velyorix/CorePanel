<?php

namespace App\Http\Middleware\Api;

use Closure;
use Core\API\Services\ApiRequestLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persist API request/response metadata after the full middleware stack.
 */
class LogApiRequest
{
    public function __construct(
        private readonly ApiRequestLogger $logger,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->logger->enabled()) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $response = $next($request);
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $this->logger->log($request, $response, $durationMs);

        return $response;
    }
}
