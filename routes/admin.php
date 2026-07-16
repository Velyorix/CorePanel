<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientMemberController;
use App\Http\Controllers\Admin\ClientInvitationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
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
