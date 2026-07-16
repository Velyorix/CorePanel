<?php

use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\ProfileController;
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

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    });
