<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     * Works for both school-admin subdomain and superadmin subdomain.
     */
    public function showLogin(Request $request): View
    {
        $isSuperAdmin = $this->isSuperAdminDomain($request);
        return view('auth.login', compact('isSuperAdmin'));
    }

    /**
     * Handle login submission.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $isSuperAdmin = $this->isSuperAdminDomain($request);

        // Attempt authentication
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput(['email' => $request->email])
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $user = Auth::user();

        // ── Super admin domain guard ──────────────────────────────────────
        if ($isSuperAdmin) {
            if (! $user->isSuperAdmin()) {
                Auth::logout();
                return back()->withErrors(['email' => 'You do not have super admin access.']);
            }

            if ($user->two_factor_enabled) {
                return $this->redirectTo2faChallenge($request, $user, 'super_admin');
            }

            $request->session()->regenerate();
            return redirect()->route('superadmin.dashboard');
        }

        // ── School admin domain guard ─────────────────────────────────────
        $tenant = app('currentTenant');

        if (! $tenant) {
            Auth::logout();
            return back()->withErrors(['email' => 'School not found.']);
        }

        if (! $user->isSchoolAdmin() || ! $user->belongsToTenant($tenant)) {
            Auth::logout();
            return back()->withErrors(['email' => 'You do not have access to this school portal.']);
        }

        if ($user->two_factor_enabled) {
            return $this->redirectTo2faChallenge($request, $user, 'school_admin');
        }

        $request->session()->regenerate();
        return redirect()->route('admin.dashboard');
    }

    /**
     * Log out and redirect to login.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect based on which domain we're on
        if ($this->isSuperAdminDomain($request)) {
            return redirect()->route('superadmin.login');
        }

        return redirect()->route('admin.login');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isSuperAdminDomain(Request $request): bool
    {
        $host   = $request->getHost();
        $domain = config('app.domain', 'localhost');
        return str_starts_with($host, 'superadmin.');
    }

    /**
     * Log the user out of the Auth guard, store pending user ID in session,
     * and redirect to the 2FA challenge form.
     */
    private function redirectTo2faChallenge(Request $request, $user, string $role): RedirectResponse
    {
        Auth::logout();
        $request->session()->regenerate();
        $request->session()->put('auth.2fa_pending_user_id', $user->id);
        $request->session()->put('auth.2fa_pending_role', $role);

        $route = $role === 'super_admin'
            ? 'superadmin.2fa.challenge'
            : 'admin.2fa.challenge';

        return redirect()->route($route);
    }
}
