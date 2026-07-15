<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('login.ratelimit');

    Route::middleware('registration.open')->group(function (): void {
        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])->name('register.store');
    });

    Route::get('register/invitation/{token}', [RegisterController::class, 'createFromInvitation'])->name('register.invitation');
    Route::post('register/invitation/{token}', [RegisterController::class, 'storeFromInvitation'])->name('register.invitation.store');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->name('logout');
