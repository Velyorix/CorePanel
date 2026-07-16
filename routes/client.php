<?php

use App\Http\Controllers\Client\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Area Routes
|--------------------------------------------------------------------------
|
| Préfixe : /client
| Middleware EnsureClient — étape 6
| CDC : Tome 7
|
*/

Route::middleware('client')
    ->prefix('client')
    ->name('client.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
