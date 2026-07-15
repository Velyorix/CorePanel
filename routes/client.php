<?php

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

Route::middleware('web')
    ->prefix('client')
    ->name('client.')
    ->group(function (): void {
        //
    });
