<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\DepartmentIsolationMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'department.isolation' => DepartmentIsolationMiddleware::class,
        ]);
        
        // REMOVED: $middleware->append(DepartmentIsolationMiddleware::class);
        // Department isolation is now applied ONLY to Academic Head routes in web.php
    })
    ->withExceptions(function (Exceptions $exceptions) {
    })->create();