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
use App\Http\Controllers\Admin\Settings\BillingSettingsController;
use App\Http\Controllers\Admin\Settings\GeneralSettingsController;
use App\Http\Controllers\Admin\Settings\MailSettingsController;
use App\Http\Controllers\Admin\Settings\SecuritySettingsController;
use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\NodeClusterController;
use App\Http\Controllers\Admin\NodeConnectionController;
use App\Http\Controllers\Admin\NodeController;
use App\Http\Controllers\Admin\NodeMonitoringController;
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
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\TicketActionController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\KbArticleController;
use App\Http\Controllers\Admin\KbArticleStatusController;
use App\Http\Controllers\Admin\KbCategoryController;
use App\Http\Controllers\Admin\SyncLogController;
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

        Route::get('settings/general', [GeneralSettingsController::class, 'edit'])->name('settings.general');
        Route::put('settings/general', [GeneralSettingsController::class, 'update'])->name('settings.general.update');
        Route::get('settings/billing', [BillingSettingsController::class, 'edit'])->name('settings.billing');
        Route::put('settings/billing', [BillingSettingsController::class, 'update'])->name('settings.billing.update');
        Route::get('settings/mail', [MailSettingsController::class, 'edit'])->name('settings.mail');
        Route::put('settings/mail', [MailSettingsController::class, 'update'])->name('settings.mail.update');
        Route::get('settings/security', [SecuritySettingsController::class, 'edit'])->name('settings.security');
        Route::put('settings/security', [SecuritySettingsController::class, 'update'])->name('settings.security.update');

        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
        Route::post('modules/{module}/install', [ModuleController::class, 'install'])->name('modules.install');
        Route::post('modules/{module}/enable', [ModuleController::class, 'enable'])->name('modules.enable');
        Route::post('modules/{module}/disable', [ModuleController::class, 'disable'])->name('modules.disable');
        Route::put('modules/{module}/config', [ModuleController::class, 'updateConfig'])->name('modules.config');
        Route::delete('modules/{module}', [ModuleController::class, 'uninstall'])->name('modules.uninstall');

        Route::get('marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
        Route::get('marketplace/updates', [MarketplaceController::class, 'updates'])->name('marketplace.updates');
        Route::post('marketplace/updates/check', [MarketplaceController::class, 'checkUpdates'])->name('marketplace.updates.check');
        Route::get('marketplace/{product}', [MarketplaceController::class, 'show'])->name('marketplace.show');
        Route::post('marketplace/{product}/install', [MarketplaceController::class, 'install'])->name('marketplace.install');

        Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');
        Route::get('themes/{theme}', [ThemeController::class, 'show'])->name('themes.show');
        Route::post('themes/preview/clear', [ThemeController::class, 'clearPreview'])->name('themes.preview.clear');
        Route::post('themes/{theme}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
        Route::post('themes/{theme}/preview', [ThemeController::class, 'preview'])->name('themes.preview');

        Route::get('gateways', [PaymentGatewayController::class, 'index'])->name('gateways.index');
        Route::get('gateways/{gateway}', [PaymentGatewayController::class, 'show'])->name('gateways.show');
        Route::post('gateways/{gateway}/enable', [PaymentGatewayController::class, 'enable'])->name('gateways.enable');
        Route::post('gateways/{gateway}/disable', [PaymentGatewayController::class, 'disable'])->name('gateways.disable');
        Route::post('gateways/{gateway}/move-up', [PaymentGatewayController::class, 'moveUp'])->name('gateways.move-up');
        Route::post('gateways/{gateway}/move-down', [PaymentGatewayController::class, 'moveDown'])->name('gateways.move-down');
        Route::put('gateways/{gateway}/config', [PaymentGatewayController::class, 'updateConfig'])->name('gateways.config');

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
        Route::post('services/{service}/sync', [SyncActionController::class, 'syncService'])->name('services.sync');

        Route::get('sync-logs', [SyncLogController::class, 'index'])->name('sync-logs.index');
        Route::get('sync-logs/{syncLog}', [SyncLogController::class, 'show'])->name('sync-logs.show');
        Route::post('sync/run', [SyncActionController::class, 'runAll'])->name('sync.run');

        Route::get('provisioning-dead-letters', [ProvisioningDeadLetterController::class, 'index'])->name('provisioning-dead-letters.index');
        Route::get('provisioning-dead-letters/{provisioningDeadLetter}', [ProvisioningDeadLetterController::class, 'show'])->name('provisioning-dead-letters.show');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/requeue', [ProvisioningDeadLetterActionController::class, 'requeue'])->name('provisioning-dead-letters.requeue');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/resolve', [ProvisioningDeadLetterActionController::class, 'resolve'])->name('provisioning-dead-letters.resolve');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/discard', [ProvisioningDeadLetterActionController::class, 'discard'])->name('provisioning-dead-letters.discard');
        Route::post('provisioning-dead-letters/{provisioningDeadLetter}/rollback', [ProvisioningDeadLetterActionController::class, 'rollback'])->name('provisioning-dead-letters.rollback');

        Route::get('nodes/monitoring', [NodeMonitoringController::class, 'index'])->name('nodes.monitoring');
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

        Route::get('node-clusters', [NodeClusterController::class, 'index'])->name('node-clusters.index');
        Route::get('node-clusters/create', [NodeClusterController::class, 'create'])->name('node-clusters.create');
        Route::post('node-clusters', [NodeClusterController::class, 'store'])->name('node-clusters.store');
        Route::get('node-clusters/{nodeCluster}', [NodeClusterController::class, 'show'])->name('node-clusters.show');
        Route::get('node-clusters/{nodeCluster}/edit', [NodeClusterController::class, 'edit'])->name('node-clusters.edit');
        Route::put('node-clusters/{nodeCluster}', [NodeClusterController::class, 'update'])->name('node-clusters.update');
        Route::delete('node-clusters/{nodeCluster}', [NodeClusterController::class, 'destroy'])->name('node-clusters.destroy');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/issue', [InvoiceActionController::class, 'issue'])->name('invoices.issue');
        Route::get('invoices/{invoice}/pdf', [InvoiceActionController::class, 'pdf'])->name('invoices.pdf');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [TicketActionController::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/assign', [TicketActionController::class, 'assign'])->name('tickets.assign');
        Route::post('tickets/{ticket}/close', [TicketActionController::class, 'close'])->name('tickets.close');
        Route::post('tickets/{ticket}/reopen', [TicketActionController::class, 'reopen'])->name('tickets.reopen');
        Route::post('tickets/{ticket}/priority', [TicketActionController::class, 'priority'])->name('tickets.priority');
        Route::get('tickets/{ticket}/attachments/{attachmentId}', [TicketActionController::class, 'downloadAttachment'])
            ->name('tickets.attachments.download');

        Route::get('kb/articles', [KbArticleController::class, 'index'])->name('kb-articles.index');
        Route::get('kb/articles/create', [KbArticleController::class, 'create'])->name('kb-articles.create');
        Route::post('kb/articles', [KbArticleController::class, 'store'])->name('kb-articles.store');
        Route::get('kb/articles/{kbArticle}', [KbArticleController::class, 'show'])->name('kb-articles.show');
        Route::get('kb/articles/{kbArticle}/edit', [KbArticleController::class, 'edit'])->name('kb-articles.edit');
        Route::put('kb/articles/{kbArticle}', [KbArticleController::class, 'update'])->name('kb-articles.update');
        Route::delete('kb/articles/{kbArticle}', [KbArticleController::class, 'destroy'])->name('kb-articles.destroy');
        Route::post('kb/articles/{kbArticle}/publish', [KbArticleStatusController::class, 'publish'])->name('kb-articles.publish');
        Route::post('kb/articles/{kbArticle}/unpublish', [KbArticleStatusController::class, 'unpublish'])->name('kb-articles.unpublish');
        Route::post('kb/articles/{kbArticle}/archive', [KbArticleStatusController::class, 'archive'])->name('kb-articles.archive');

        Route::get('kb/categories', [KbCategoryController::class, 'index'])->name('kb-categories.index');
        Route::get('kb/categories/create', [KbCategoryController::class, 'create'])->name('kb-categories.create');
        Route::post('kb/categories', [KbCategoryController::class, 'store'])->name('kb-categories.store');
        Route::get('kb/categories/{kbCategory}/edit', [KbCategoryController::class, 'edit'])->name('kb-categories.edit');
        Route::put('kb/categories/{kbCategory}', [KbCategoryController::class, 'update'])->name('kb-categories.update');
        Route::delete('kb/categories/{kbCategory}', [KbCategoryController::class, 'destroy'])->name('kb-categories.destroy');

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
