<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectPendingStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'siswa_pending') {
            $id = $request->user()->id;
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('lms_student_registration_pending_id', $id);
            return redirect()->route('register.verification');
        }
        return $next($request);
    }
}
