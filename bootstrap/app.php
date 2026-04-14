<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['throttle:60,1', 'throttle:horizon-stream', \Illuminate\Routing\Middleware\SubstituteBindings::class])
                ->prefix('horizon')
                ->name('horizon.')
                ->group(base_path('routes/streams.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
