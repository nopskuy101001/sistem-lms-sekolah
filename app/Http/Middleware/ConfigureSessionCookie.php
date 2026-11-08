<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('session.secure_auto', true)) {
            config()->set('session.secure', $request->isSecure());
        }

        return $next($request);
    }
}
