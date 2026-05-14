<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('superadmin.login');
        }

        if (! Auth::user()->isSuperAdmin()) {
            Auth::logout();
            return redirect()->route('superadmin.login')
                ->withErrors(['email' => 'Super admin access required.']);
        }

        return $next($request);
    }
}
