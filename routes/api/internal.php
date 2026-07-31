<?php

use App\Http\Controllers\Api\Internal\InternalNodeController;
use App\Http\Controllers\Api\Internal\InternalServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal API (Modules)
|--------------------------------------------------------------------------
|
| Prefixed with /api/internal and wrapped by the api.internal middleware group.
|
*/

Route::post('service/create', [InternalServiceController::class, 'create'])
    ->name('service.create');
Route::post('service/suspend', [InternalServiceController::class, 'suspend'])
    ->name('service.suspend');
Route::post('service/terminate', [InternalServiceController::class, 'terminate'])
    ->name('service.terminate');
Route::post('node/sync', [InternalNodeController::class, 'sync'])
    ->name('node.sync');
