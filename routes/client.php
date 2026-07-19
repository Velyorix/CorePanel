<?php

use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\CatalogController;
use App\Http\Controllers\Client\CheckoutController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\InvoicePaymentController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\ProfileController;
use App\Http\Controllers\Client\QuoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Area Routes
|--------------------------------------------------------------------------
|
| Préfixe : /client
| Middleware EnsureClient
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

        Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::patch('checkout/coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon.apply');
        Route::get('checkout/complete', [CheckoutController::class, 'complete'])->name('checkout.complete');
        Route::post('checkout/place', [CheckoutController::class, 'placeOrder'])->name('checkout.place');
        Route::get('checkout/orders/{order}', [CheckoutController::class, 'placed'])->name('checkout.placed');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/pay', [InvoicePaymentController::class, 'pay'])->name('invoices.pay');

        Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
        Route::post('quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
        Route::post('quotes/{quote}/decline', [QuoteController::class, 'decline'])->name('quotes.decline');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    });
