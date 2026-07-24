<?php

namespace Core\API\Http;

use Core\API\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Render Laravel exceptions as the standard API error envelope for /api/v1/*.
 */
final class ApiExceptionRenderer
{
    public function shouldRender(Request $request): bool
    {
        return $request->is('api/v1', 'api/v1/*');
    }

    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $this->shouldRender($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return ApiResponse::error(
                'validation_failed',
                __('The given data was invalid.'),
                422,
                ['fields' => $e->errors()],
            );
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiResponse::error(
                'not_found',
                __('Resource not found.'),
                404,
            );
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(
                'unauthenticated',
                __('Unauthenticated.'),
                401,
            );
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $details = [];

            if ($status === 429) {
                $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 0);
                if ($retryAfter > 0) {
                    $details['retry_after'] = $retryAfter;
                }
            }

            return ApiResponse::error(
                $this->codeForStatus($status),
                $e->getMessage() !== '' ? $e->getMessage() : $this->defaultMessageForStatus($status),
                $status,
                $details,
            );
        }

        return ApiResponse::error(
            'server_error',
            config('app.debug') ? $e->getMessage() : __('Server error.'),
            500,
        );
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            415 => 'unsupported_media_type',
            422 => 'validation_failed',
            429 => 'rate_limit_exceeded',
            503 => 'service_unavailable',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };
    }

    private function defaultMessageForStatus(int $status): string
    {
        return match ($status) {
            401 => __('Unauthenticated.'),
            403 => __('Forbidden.'),
            404 => __('Resource not found.'),
            405 => __('Method not allowed.'),
            415 => __('Unsupported media type.'),
            422 => __('The given data was invalid.'),
            429 => __('Too many requests.'),
            503 => __('Service unavailable.'),
            default => __('Request failed.'),
        };
    }
}
