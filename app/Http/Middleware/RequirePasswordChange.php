<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password
            && ! $request->routeIs(
                'password.change', 'password.update', 'logout',
                'password.request', 'password.email', 'password.reset', 'password.store'
            )) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
