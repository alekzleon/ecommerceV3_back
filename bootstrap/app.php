<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(HandleCors::class);
        $middleware->alias([
            'module' => \App\Http\Middleware\CheckModuleAccess::class,
            'manage_access' => \App\Http\Middleware\EnsureUserCanManageAccess::class,
            'not_client' => \App\Http\Middleware\EnsureUserIsNotClient::class,
            'admin_or_super_admin' => \App\Http\Middleware\EnsureUserIsAdminOrSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
