<?php

namespace Core\API\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Standard JSON envelope for the public REST API: { data, meta } | { error }.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data, ?Request $request = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge(self::metaFromRequest($request), $meta),
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $details = [],
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function metaFromRequest(?Request $request): array
    {
        if ($request === null) {
            return [];
        }

        $requestId = $request->attributes->get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            $header = (string) config('corepanel.api.request_id_header', 'X-Request-Id');
            $requestId = trim((string) $request->headers->get($header, ''));
        }

        return filled($requestId) ? ['request_id' => $requestId] : [];
    }
}
