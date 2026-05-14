<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Not logged in → redirect to login
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user   = Auth::user();
        $tenant = app('currentTenant');

        // Must be a school admin belonging to this tenant
        if (! $user->isSchoolAdmin() || ! $tenant || ! $user->belongsToTenant($tenant)) {
            Auth::logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Access denied for this school.']);
        }

        return $next($request);
    }
}
