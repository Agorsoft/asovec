<?php

use App\Http\Middleware\CheckInstallation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepend installer check middleware so requests are redirected
        // al instalador cuando APP_INSTALLED=false en .env
        $middleware->prepend([CheckInstallation::class]);
    })
    // ->withMiddleware(function (Middleware $middleware): void {
    //     $middleware->redirectUsersTo(fn (): string => route('filament.admin.pages.dashboard'));
    // })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
