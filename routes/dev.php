<?php

use App\Http\Controllers\Dev\ComponentShowcaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Development Routes
|--------------------------------------------------------------------------
|
| Component showcase and other local tooling. Access is gated in the
| controller (local / COREPANEL_UI_SHOWCASE) — CDC Étape 4.8.
|
*/

Route::middleware('web')
    ->prefix('dev')
    ->name('dev.')
    ->group(function (): void {
        Route::get('components', ComponentShowcaseController::class)->name('components');
    });
