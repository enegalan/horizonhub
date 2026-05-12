<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware([SubstituteBindings::class])
                ->prefix('horizon')
                ->name('horizon.')
                ->group(base_path('routes/streams.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('horizon/streams/*')) {
                return null;
            }

            if ($request->is('horizon', 'horizon/*')) {
                return redirect()->route('horizon.index');
            }

            return null;
        });
    })->create();
