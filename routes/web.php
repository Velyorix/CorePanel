<?php

use App\Http\Controllers\Account\UserSessionController;
use App\Http\Controllers\Install\LicenseActivationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('install/license', [LicenseActivationController::class, 'create'])->name('install.license.create');
    Route::post('install/license', [LicenseActivationController::class, 'store'])->name('install.license.store');
});

Route::get('/dashboard', function () {
    $rows = collect([
        ['name' => 'Acme Corp', 'status' => 'active'],
        ['name' => 'Nova Cloud', 'status' => 'pending'],
        ['name' => 'Orbit Labs', 'status' => 'active'],
        ['name' => 'Pioneer Host', 'status' => 'pending'],
        ['name' => 'Zenith Nodes', 'status' => 'active'],
        ['name' => 'Blue Harbor', 'status' => 'active'],
        ['name' => 'Cedar Stack', 'status' => 'pending'],
        ['name' => 'Delta Forge', 'status' => 'active'],
    ]);

    if (filled(request('q'))) {
        $query = strtolower((string) request('q'));
        $rows = $rows->filter(
            fn (array $row): bool => str_contains(strtolower($row['name']), $query)
                || str_contains(strtolower($row['status']), $query),
        )->values();
    }

    $sort = request('sort');
    $dir = strtolower((string) request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

    if (in_array($sort, ['name', 'status'], true)) {
        $rows = $rows->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE, $dir === 'desc')->values();
    }

    $demoRows = new \Illuminate\Pagination\LengthAwarePaginator(
        items: $rows->forPage((int) request('page', 1), 4)->values(),
        total: $rows->count(),
        perPage: 4,
        currentPage: (int) request('page', 1),
        options: [
            'path' => route('dashboard'),
            'query' => request()->query(),
        ],
    );

    return view('dashboard', compact('demoRows'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function (): void {
    Route::get('sessions', [UserSessionController::class, 'index'])->name('sessions.index');
    Route::delete('sessions/{userSession}', [UserSessionController::class, 'destroy'])->name('sessions.destroy');
    Route::post('sessions/revoke-others', [UserSessionController::class, 'destroyOthers'])->name('sessions.revoke-others');
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

    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->name('verification.send');
});
