<?php

namespace App\Http\Middleware\Api;

use Closure;
use Core\API\Models\ApiToken;
use Core\API\Services\ApiTokenScopeChecker;
use Core\API\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated API token has the required scopes (AND).
 */
class EnsureApiScope
{
    public function __construct(
        private readonly ApiTokenScopeChecker $scopes,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $required = array_values(array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            $requiredScopes,
        )));

        if ($required === []) {
            abort(500, 'EnsureApiScope requires at least one scope.');
        }

        $token = $request->attributes->get('api_token');

        if (! $token instanceof ApiToken) {
            return ApiResponse::error('unauthenticated', __('API token missing.'), 401);
        }

        if (! $this->scopes->allows($token, $required)) {
            return ApiResponse::error(
                'insufficient_scope',
                __('This API token does not have the required scope for this endpoint.'),
                403,
                ['required_scopes' => $required],
            );
        }

        return $next($request);
    }
}
