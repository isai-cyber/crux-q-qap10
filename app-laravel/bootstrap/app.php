<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);

        // /mcp vive en la raíz (no bajo /api) y clientes MCP externos no
        // envían token CSRF: se excluye explícitamente (ver routes/web.php).
        $middleware->validateCsrfTokens(except: ['mcp']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
