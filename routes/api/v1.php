<?php

use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NodeController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api/v1 and wrapped by the api.v1 middleware group.
|
*/

Route::match(['GET', 'POST'], 'ping', PingController::class)->name('ping');

Route::middleware('api.auth')->group(function (): void {
    Route::get('me', MeController::class)
        ->middleware('api.scope:api.me')
        ->name('me');

    Route::get('clients', [ClientController::class, 'index'])
        ->middleware('api.scope:api.client.read')
        ->name('clients.index');
    Route::get('clients/{client}', [ClientController::class, 'show'])
        ->middleware('api.scope:api.client.read')
        ->name('clients.show');

    Route::get('services', [ServiceController::class, 'index'])
        ->middleware('api.scope:api.service.read')
        ->name('services.index');
    Route::get('services/{service}', [ServiceController::class, 'show'])
        ->middleware('api.scope:api.service.read')
        ->name('services.show');
    Route::post('services/{service}/restart', [ServiceController::class, 'restart'])
        ->middleware('api.scope:api.service.write')
        ->name('services.restart');
    Route::post('services/{service}/suspend', [ServiceController::class, 'suspend'])
        ->middleware('api.scope:api.service.write')
        ->name('services.suspend');

    Route::get('invoices', [InvoiceController::class, 'index'])
        ->middleware('api.scope:api.invoice.read')
        ->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('api.scope:api.invoice.read')
        ->name('invoices.show');
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])
        ->middleware('api.scope:api.invoice.write')
        ->name('invoices.pay');

    Route::get('tickets', [TicketController::class, 'index'])
        ->middleware('api.scope:api.ticket.read')
        ->name('tickets.index');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])
        ->middleware('api.scope:api.ticket.read')
        ->name('tickets.show');
    Route::post('tickets', [TicketController::class, 'store'])
        ->middleware('api.scope:api.ticket.write')
        ->name('tickets.store');
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])
        ->middleware('api.scope:api.ticket.write')
        ->name('tickets.reply');

    Route::get('nodes', [NodeController::class, 'index'])
        ->middleware('api.scope:api.node.read')
        ->name('nodes.index');
    Route::get('nodes/{node}', [NodeController::class, 'show'])
        ->middleware('api.scope:api.node.read')
        ->name('nodes.show');

    Route::get('webhooks/events', [WebhookController::class, 'events'])
        ->middleware('api.scope:api.webhook.read')
        ->name('webhooks.events');
    Route::get('webhooks', [WebhookController::class, 'index'])
        ->middleware('api.scope:api.webhook.read')
        ->name('webhooks.index');
    Route::post('webhooks', [WebhookController::class, 'store'])
        ->middleware('api.scope:api.webhook.write')
        ->name('webhooks.store');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])
        ->middleware('api.scope:api.webhook.read')
        ->name('webhooks.show');
    Route::patch('webhooks/{webhook}', [WebhookController::class, 'update'])
        ->middleware('api.scope:api.webhook.write')
        ->name('webhooks.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])
        ->middleware('api.scope:api.webhook.write')
        ->name('webhooks.destroy');
    Route::post('webhooks/{webhook}/rotate-secret', [WebhookController::class, 'rotateSecret'])
        ->middleware('api.scope:api.webhook.write')
        ->name('webhooks.rotate-secret');
    Route::get('webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries'])
        ->middleware('api.scope:api.webhook.read')
        ->name('webhooks.deliveries');
});
