<?php

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\PrepareInstallerRuntime;
use App\Http\Middleware\RequireWorkspaceCapability;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\UseWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [PrepareInstallerRuntime::class], append: [SetLocale::class]);
        $middleware->alias([
            'installed' => EnsureInstalled::class,
            'workspace' => UseWorkspace::class,
            'workspace.capability' => RequireWorkspaceCapability::class,
        ]);
        // Tenant context must exist before implicit route-model binding queries any
        // BelongsToWorkspace model. Authentication remains ahead of this middleware
        // in Laravel's default priority list.
        $middleware->prependToPriorityList(SubstituteBindings::class, UseWorkspace::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
