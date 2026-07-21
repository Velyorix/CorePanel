<?php

use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api (bootstrap/app.php). Payment provider webhooks are
| unauthenticated — each gateway verifies its own signature.
|
*/

Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');

Route::prefix('v1')->name('v1.')->group(function (): void {
    //
});
