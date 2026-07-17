<?php

use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\CatalogController;
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

        Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('catalog/categories/{category}', [CatalogController::class, 'category'])->name('catalog.category');
        Route::get('catalog/products/{product}/configure', [CatalogController::class, 'configure'])->name('catalog.products.configure');
        Route::post('catalog/products/{product}/configure', [CatalogController::class, 'store'])->name('catalog.products.configure.store');
        Route::post('catalog/products/{product}/configure/preview', [CatalogController::class, 'preview'])->name('catalog.products.configure.preview');
        Route::get('catalog/products/{product}', [CatalogController::class, 'show'])->name('catalog.products.show');

        Route::get('cart', [CartController::class, 'index'])->name('cart.index');
        Route::patch('cart/items/{item}', [CartController::class, 'updateQuantity'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [CartController::class, 'destroyItem'])->name('cart.items.destroy');
        Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    });
