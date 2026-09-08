<?php

use App\Http\Middleware\EnsureCustomerIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Both panels are same-origin, so their JSON APIs ride the session
            // and CSRF of the web group rather than a stateless token guard.
            Route::middleware(['web', EnsureCustomerIsActive::class])
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->prefix('admin/api')
                ->name('admin.api.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind the hosting platform's proxy the scheme and the client address
        // only arrive in the forwarded headers, and HTTPS detection drives both
        // secure cookies and generated URLs.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'admin/api/*') || $request->expectsJson(),
        );
    })->create();
