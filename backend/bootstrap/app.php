<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Add your custom CORS middleware to the global middleware stack
        $middleware->append([
            \App\Http\Middleware\CorsMiddleware::class
        ]);

        // Or if you want it to run first:
        // $middleware->prepend([
        //     \App\Http\Middleware\CorsMiddleware::class
        // ]);

        // Alternatively, you could add it to specific middleware groups:
        // $middleware->web([\App\Http\Middleware\CorsMiddleware::class]);
        // $middleware->api([\App\Http\Middleware\CorsMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
