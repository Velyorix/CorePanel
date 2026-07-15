<?php

use App\Http\Middleware\EnsureLoginIsNotRateLimited;
use App\Http\Middleware\EnforceMaintenanceMode;
use App\Http\Middleware\SetLocale;
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
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            EnforceMaintenanceMode::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
