<?php

namespace Core\API\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Global rate limiting for public REST API routes (/api/v1).
 */
class ApiRateLimiter
{
    public function enabled(): bool
    {
        return (bool) config('corepanel.api.rate_limit.enabled', false);
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('corepanel.api.rate_limit.max_attempts', 60));
    }

    public function decaySeconds(): int
    {
        return max(1, (int) config('corepanel.api.rate_limit.decay_seconds', 60));
    }

    public function key(Request $request): string
    {
        $route = $request->route()?->getName()
            ?? trim($request->path(), '/');

        $identity = $this->identityKey($request);

        return 'api:'.$identity.'|'.$route;
    }

    public function tooManyAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->key($request), $this->maxAttempts());
    }

    public function hit(Request $request): void
    {
        RateLimiter::hit($this->key($request), $this->decaySeconds());
    }

    public function remaining(Request $request): int
    {
        return max(0, RateLimiter::remaining($this->key($request), $this->maxAttempts()));
    }

    public function retryAfter(Request $request): int
    {
        return max(0, RateLimiter::availableIn($this->key($request)));
    }

    public function resetAt(Request $request): int
    {
        return now()->getTimestamp() + $this->retryAfter($request);
    }

    private function identityKey(Request $request): string
    {
        $bearer = $this->bearerToken($request);

        if ($bearer !== null) {
            return 'token:'.hash('sha256', $bearer);
        }

        return 'ip:'.hash('sha256', (string) ($request->ip() ?? 'unknown'));
    }

    private function bearerToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));

        if ($header === '' || ! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token !== '' ? $token : null;
    }
}
