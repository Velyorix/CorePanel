<?php

namespace App\Http\Middleware\Api;

use Closure;
use Core\API\Services\ApiTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate API requests via Bearer tokens (cpat_…).
 */
class AuthenticateApiToken
{
    public function __construct(
        private readonly ApiTokenService $tokens,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $this->bearerToken($request);

        if ($plainText === null) {
            return $this->unauthenticated(__('API token missing.'));
        }

        $token = $this->tokens->findValidByPlainText($plainText);

        if ($token === null) {
            return $this->unauthenticated(__('Invalid or expired API token.'));
        }

        $user = $token->user;

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('api_token', $token);

        $this->tokens->touchLastUsed($token);

        return $next($request);
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

    private function unauthenticated(string $message): Response
    {
        return response()->json([
            'error' => [
                'code' => 'unauthenticated',
                'message' => $message,
            ],
        ], 401);
    }
}
