<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Préfixe global : /api (via bootstrap/app.php)
| Version : /api/v1/*
| Middleware stack API
|
*/

Route::prefix('v1')->name('v1.')->group(function (): void {
    //
});
