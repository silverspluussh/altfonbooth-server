<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->group(base_path('routes/api_v1.php'));

            $internalThrottle = (int) config('services.ussd.internal_throttle_per_minute', 1200);

            Route::middleware(['api', 'request.id', 'internal.token', "throttle:{$internalThrottle},1"])
                ->prefix('api/internal/ussd')
                ->group(base_path('routes/internal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.role' => \App\Http\Middleware\AdminRoleMiddleware::class,
            'v1.normalize' => \App\Http\Middleware\V1NormalizeRequest::class,
            'v1.envelope' => \App\Http\Middleware\V1ResponseEnvelope::class,
            'internal.token' => \App\Http\Middleware\InternalTokenMiddleware::class,
            'request.id' => \App\Http\Middleware\AssignRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'status' => false,
                    'error' => [
                        'code' => 401,
                        'message' => 'Token is not defined.',
                    ],
                    'reason' => 'Unauthenticated.',
                ], 401);
            }

            return null;
        });
    })->create();
