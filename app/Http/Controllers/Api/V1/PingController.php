<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\API\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'message' => 'pong',
            'api_version' => (string) config('corepanel.api.version', 'v1'),
        ], $request);
    }
}
