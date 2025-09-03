<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: [
            __DIR__ . '/../routes/api_auth.php',
            __DIR__ . '/../routes/api_streamers.php',
            __DIR__ . '/../routes/api_admin.php',
            __DIR__ . '/../routes/api_payments.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Register custom middleware
        $middleware->alias([
            'streamer.token' => \App\Http\Middleware\EnsureStreamerToken::class,
            'admin.token' => \App\Http\Middleware\EnsureAdminToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
