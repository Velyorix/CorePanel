<?php

namespace Core\API\Services;

use Core\API\Models\ApiRequestLog;
use Core\API\Models\ApiToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Persist HTTP request metadata for /api/v1 audit and debugging.
 */
class ApiRequestLogger
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'authorization',
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'client_secret',
    ];

    public function enabled(): bool
    {
        return (bool) config('corepanel.api.request_log.enabled', true);
    }

    public function log(Request $request, Response $response, int $durationMs): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $token = $request->attributes->get('api_token');
            $tokenId = $token instanceof ApiToken ? $token->id : null;
            $user = $request->user();

            ApiRequestLog::query()->create([
                'request_id' => $this->requestId($request),
                'user_id' => $user?->id,
                'api_token_id' => $tokenId,
                'method' => strtoupper($request->getMethod()),
                'path' => '/'.ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'query' => $this->redactedQuery($request),
                'status_code' => $response->getStatusCode(),
                'error_code' => $this->errorCode($response),
                'ip_address' => $request->ip(),
                'user_agent' => $this->truncate((string) $request->userAgent(), 1024),
                'api_version' => $this->apiVersion($request),
                'duration_ms' => max(0, $durationMs),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never break the API response because logging failed.
        }
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('request_id');

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        $header = (string) config('corepanel.api.request_id_header', 'X-Request-Id');

        return trim((string) $request->headers->get($header, '')) ?: 'unknown';
    }

    private function apiVersion(Request $request): ?string
    {
        $version = $request->attributes->get('api_version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function redactedQuery(Request $request): ?array
    {
        $query = $request->query();

        if ($query === []) {
            return null;
        }

        return $this->redact($query);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function redact(array $input): array
    {
        $redacted = [];

        foreach ($input as $key => $value) {
            $name = strtolower((string) $key);

            if (in_array($name, self::SENSITIVE_KEYS, true) || str_contains($name, 'secret') || str_contains($name, 'password') || str_contains($name, 'token')) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redact($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function errorCode(Response $response): ?string
    {
        if ($response->getStatusCode() < 400) {
            return null;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return null;
        }

        $code = $decoded['error']['code'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function truncate(string $value, int $max): ?string
    {
        if ($value === '') {
            return null;
        }

        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max).'…';
    }
}
