<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'pong',
            'api_version' => (string) config('corepanel.api.version', 'v1'),
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }
}
