<?php

use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\CatalogController;
use App\Http\Controllers\Client\CheckoutController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\InvoicePaymentController;
use App\Http\Controllers\Client\MarketplaceController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\ProfileController;
use App\Http\Controllers\Client\QuoteController;
use App\Http\Controllers\Client\ServiceActionController;
use App\Http\Controllers\Client\ServiceController;
use App\Http\Controllers\Client\TicketActionController;
use App\Http\Controllers\Client\TicketController;
use App\Http\Controllers\Client\KnowledgeBaseController;
use App\Http\Controllers\Client\NotificationPreferencesController;
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

        Route::get('marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
        Route::get('marketplace/purchases', [MarketplaceController::class, 'purchases'])->name('marketplace.purchases');
        Route::get('marketplace/{product}', [MarketplaceController::class, 'show'])->name('marketplace.show');

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

        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
        Route::post('services/{service}/start', [ServiceActionController::class, 'start'])->name('services.start');
        Route::post('services/{service}/stop', [ServiceActionController::class, 'stop'])->name('services.stop');
        Route::post('services/{service}/restart', [ServiceActionController::class, 'restart'])->name('services.restart');
        Route::post('services/{service}/reinstall', [ServiceActionController::class, 'reinstall'])->name('services.reinstall');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/pay', [InvoicePaymentController::class, 'pay'])->name('invoices.pay');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [TicketActionController::class, 'reply'])->name('tickets.reply');
        Route::get('tickets/{ticket}/attachments/{attachmentId}', [TicketActionController::class, 'downloadAttachment'])
            ->name('tickets.attachments.download');

        Route::get('kb', [KnowledgeBaseController::class, 'index'])->name('kb.index');
        Route::get('kb/{slug}', [KnowledgeBaseController::class, 'show'])->name('kb.show');

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

        Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])
            ->name('settings.notifications');
        Route::put('settings/notifications', [NotificationPreferencesController::class, 'update'])
            ->name('settings.notifications.update');
    });
