<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RedirectToHttps;
use App\Http\Middleware\RequireConfirmation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            // Actions graves : page de confirmation si le formulaire arrive sans confirmed=1.
            'confirm' => RequireConfirmation::class,
        ]);

        // HTTPS obligatoire en production (avant tout le reste du groupe web).
        $middleware->prependToGroup('web', RedirectToHttps::class);

        // Mot de passe provisoire : changement obligatoire avant tout le reste.
        $middleware->appendToGroup('web', EnsurePasswordIsChanged::class);

        $middleware->redirectUsersTo(
            fn (Request $request) => AuthenticatedSessionController::homeFor($request->user()),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
