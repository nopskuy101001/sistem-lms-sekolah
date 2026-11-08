<?php

use App\Http\Middleware\ConfigureSessionCookie;
use App\Http\Middleware\RejectPendingStudent;
use App\Http\Middleware\RequirePasswordChange;
use App\Support\TrustedHostRegistry;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->trustHosts(
            at: fn (): array => app(TrustedHostRegistry::class)->patterns(),
            subdomains: false,
        );

        $middleware->replace(
            TrustHosts::class,
            App\Http\Middleware\TrustHosts::class,
        );

        $middleware->web(
            prepend: [ConfigureSessionCookie::class],
            append: [
                RequirePasswordChange::class,
                RejectPendingStudent::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
