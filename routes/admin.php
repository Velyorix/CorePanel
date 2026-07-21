<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientMemberController;
use App\Http\Controllers\Admin\ClientInvitationController;
use App\Http\Controllers\Admin\ClientStatusController;
use App\Http\Controllers\Admin\ClientImpersonationController;
use App\Http\Controllers\Admin\ClientNoteController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceActionController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\NodeConnectionController;
use App\Http\Controllers\Admin\NodeController;
use App\Http\Controllers\Admin\NodeGroupController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderStatusController;
use App\Http\Controllers\Admin\PaymentActionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\QuoteActionController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductStatusController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceActionController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ProvisioningDeadLetterActionController;
use App\Http\Controllers\Admin\ProvisioningDeadLetterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
|
| Préfixe : /admin
| Middleware EnsureAdmin
|
*/

Route::middleware('admin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

        Route::get('license', [LicenseController::class, 'show'])->name('license.show');
        Route::put('license', [LicenseController::class, 'update'])->name('license.update');
        Route::post('license/revalidate', [LicenseController::class, 'revalidate'])->name('license.revalidate');

        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        Route::post('clients/{client}/members', [ClientMemberController::class, 'store'])->name('clients.members.store');
        Route::put('clients/{client}/members/{membership}', [ClientMemberController::class, 'update'])->name('clients.members.update');
        Route::delete('clients/{client}/members/{membership}', [ClientMemberController::class, 'destroy'])->name('clients.members.destroy');

        Route::post('clients/{client}/invitations', [ClientInvitationController::class, 'store'])->name('clients.invitations.store');

        Route::post('clients/{client}/suspend', [ClientStatusController::class, 'suspend'])->name('clients.suspend');
        Route::post('clients/{client}/unsuspend', [ClientStatusController::class, 'unsuspend'])->name('clients.unsuspend');
        Route::post('clients/{client}/close', [ClientStatusController::class, 'close'])->name('clients.close');
        Route::post('clients/{client}/reopen', [ClientStatusController::class, 'reopen'])->name('clients.reopen');
        Route::post('clients/{client}/impersonate', [ClientImpersonationController::class, 'store'])->name('clients.impersonate');

        Route::post('clients/{client}/notes', [ClientNoteController::class, 'store'])->name('clients.notes.store');
        Route::delete('clients/{client}/notes/{note}', [ClientNoteController::class, 'destroy'])->name('clients.notes.destroy');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('products/{product}/publish', [ProductStatusController::class, 'publish'])->name('products.publish');
        Route::post('products/{product}/unpublish', [ProductStatusController::class, 'unpublish'])->name('products.unpublish');
        Route::post('products/{product}/archive', [ProductStatusController::class, 'archive'])->name('products.archive');
        Route::post('products/{product}/restore', [ProductStatusController::class, 'restore'])->name('products.restore');

        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::get('product-categories/create', [ProductCategoryController::class, 'create'])->name('product-categories.create');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
        Route::get('product-categories/{productCategory}', [ProductCategoryController::class, 'show'])->name('product-categories.show');
        Route::get('product-categories/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->name('product-categories.edit');
        Route::put('product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
        Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/mark-pending-payment', [OrderStatusController::class, 'markPendingPayment'])->name('orders.mark-pending-payment');
        Route::post('orders/{order}/mark-paid', [OrderStatusController::class, 'markPaid'])->name('orders.mark-paid');
        Route::post('orders/{order}/cancel', [OrderStatusController::class, 'cancel'])->name('orders.cancel');

        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
        Route::post('services/{service}/start', [ServiceActionController::class, 'start'])->name('services.start');
        Route::post('services/{service}/stop', [ServiceActionController::class, 'stop'])->name('services.stop');
        Route::post('services/{service}/restart', [ServiceActionController::class, 'restart'])->name('services.restart');
        Route::post('services/{service}/suspend', [ServiceActionController::class, 'suspend'])->name('services.suspend');
        Route::post('services/{service}/unsuspend', [ServiceActionController::class, 'unsuspend'])->name('services.unsuspend');
        Route::post('services/{service}/terminate', [ServiceActionController::class, 'terminate'])->name('services.terminate');
        Route::post('services/{service}/reinstall', [ServiceActionController::class, 'reinstall'])->name('services.reinstall');

        Route::get('provisioning-dead-letters', [ProvisioningDeadLetterController::class, 'index'])->name('provisioning-dead-letters.index');
        Route::get('provisioning-dead-letters/{provisioningDeadLetter}', [ProvisioningDeadLetterController::class, 'show'])->name('provisioning-dead-letters.show');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/requeue', [ProvisioningDeadLetterActionController::class, 'requeue'])->name('provisioning-dead-letters.requeue');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/resolve', [ProvisioningDeadLetterActionController::class, 'resolve'])->name('provisioning-dead-letters.resolve');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/discard', [ProvisioningDeadLetterActionController::class, 'discard'])->name('provisioning-dead-letters.discard');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/rollback', [ProvisioningDeadLetterActionController::class, 'rollback'])->name('provisioning-dead-letters.rollback');

        Route::get('nodes', [NodeController::class, 'index'])->name('nodes.index');
        Route::get('nodes/create', [NodeController::class, 'create'])->name('nodes.create');
        Route::get('nodes/{node}', [NodeController::class, 'show'])->name('nodes.show');
        Route::post('nodes', [NodeController::class, 'store'])->name('nodes.store');
        Route::post('nodes/test-connection', [NodeConnectionController::class, 'test'])->name('nodes.test-connection');
        Route::post('nodes/{node}/test-connection', [NodeConnectionController::class, 'testNode'])->name('nodes.test-connection.node');
        Route::post('nodes/{node}/sync', [NodeController::class, 'sync'])->name('nodes.sync');
        Route::post('nodes/{node}/maintenance', [NodeController::class, 'maintenance'])->name('nodes.maintenance');
        Route::post('nodes/{node}/enable', [NodeController::class, 'enable'])->name('nodes.enable');
        Route::post('nodes/{node}/disable', [NodeController::class, 'disable'])->name('nodes.disable');
        Route::delete('nodes/{node}', [NodeController::class, 'destroy'])->name('nodes.destroy');

        Route::get('node-groups', [NodeGroupController::class, 'index'])->name('node-groups.index');
        Route::get('node-groups/create', [NodeGroupController::class, 'create'])->name('node-groups.create');
        Route::post('node-groups', [NodeGroupController::class, 'store'])->name('node-groups.store');
        Route::get('node-groups/{nodeGroup}', [NodeGroupController::class, 'show'])->name('node-groups.show');
        Route::get('node-groups/{nodeGroup}/edit', [NodeGroupController::class, 'edit'])->name('node-groups.edit');
        Route::put('node-groups/{nodeGroup}', [NodeGroupController::class, 'update'])->name('node-groups.update');
        Route::delete('node-groups/{nodeGroup}', [NodeGroupController::class, 'destroy'])->name('node-groups.destroy');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/issue', [InvoiceActionController::class, 'issue'])->name('invoices.issue');
        Route::get('invoices/{invoice}/pdf', [InvoiceActionController::class, 'pdf'])->name('invoices.pdf');

        Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::post('quotes/{quote}/send', [QuoteActionController::class, 'send'])->name('quotes.send');
        Route::post('quotes/{quote}/convert', [QuoteActionController::class, 'convert'])->name('quotes.convert');
        Route::post('quotes/{quote}/cancel', [QuoteActionController::class, 'cancel'])->name('quotes.cancel');
        Route::get('quotes/{quote}/pdf', [QuoteActionController::class, 'pdf'])->name('quotes.pdf');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/complete', [PaymentActionController::class, 'complete'])->name('payments.complete');
        Route::post('payments/{payment}/fail', [PaymentActionController::class, 'fail'])->name('payments.fail');

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');
    });
