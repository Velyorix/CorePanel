<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureValidLicense;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureLoginIsNotRateLimited;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnforceMaintenanceMode;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackAuthenticatedSession;
use Core\Themes\Http\Middleware\ApplyEffectiveTheme;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/admin.php');
            require base_path('routes/client.php');
            require base_path('routes/dev.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            EnforceMaintenanceMode::class,
        ]);

        $middleware->web(append: [
            ApplyEffectiveTheme::class,
            SetLocale::class,
            TrackAuthenticatedSession::class,
        ]);

        $middleware->api(prepend: [
            EnforceMaintenanceMode::class,
            SetLocale::class,
        ]);

        $middleware->preventRequestsDuringMaintenance(except: [
            'up',
        ]);

        $middleware->alias([
            'locale' => SetLocale::class,
            'maintenance' => EnforceMaintenanceMode::class,
            'login.ratelimit' => EnsureLoginIsNotRateLimited::class,
            'registration.open' => EnsureRegistrationIsOpen::class,
            'verified' => EnsureEmailIsVerified::class,
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
            'admin' => EnsureAdmin::class,
            'client' => EnsureClient::class,
            'license.valid' => EnsureValidLicense::class,
        ]);

        $middleware->group('admin', [
            'web',
            EnsureAdmin::class,
            EnsureValidLicense::class,
        ]);

        $middleware->group('client', [
            'web',
            EnsureClient::class,
            EnsureValidLicense::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
