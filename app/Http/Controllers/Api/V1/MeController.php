<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\API\Models\ApiToken;
use Core\API\Support\ApiScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->attributes->get('api_token');

        $scopes = null;

        if ($token instanceof ApiToken) {
            $scopes = $token->permissions === null
                ? [ApiScope::ALL]
                : array_values($token->permissions ?? []);
        }

        return response()->json([
            'id' => $user?->id,
            'name' => $user?->name,
            'email' => $user?->email,
            'scopes' => $scopes,
        ]);
    }
}
