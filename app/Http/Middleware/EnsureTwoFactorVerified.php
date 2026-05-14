<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * After the Laravel Auth guard authenticates a user,
 * if that user has 2FA enabled we require a valid TOTP
 * confirmation before granting access to protected routes.
 *
 * The session key `auth.2fa_verified` is set to true after a
 * successful TOTP challenge (or when 2FA is disabled for the account).
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->two_factor_enabled && ! $request->session()->get('auth.2fa_verified')) {
            // Determine the correct challenge route based on domain
            $host         = $request->getHost();
            $isSuperAdmin = str_starts_with($host, 'superadmin.');
            $route        = $isSuperAdmin ? 'superadmin.2fa.challenge' : 'admin.2fa.challenge';

            return redirect()->route($route);
        }

        return $next($request);
    }
}
