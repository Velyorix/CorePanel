<?php

use App\Http\Controllers\Api\V1\PingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api/v1 and wrapped by the api.v1 middleware group.
|
*/

Route::match(['GET', 'POST'], 'ping', PingController::class)->name('ping');
