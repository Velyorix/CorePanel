<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
|
| Préfixe : /admin
| Middleware EnsureAdmin — étape 5
| CDC : Tome 6
|
*/

Route::middleware('web')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        //
    });
