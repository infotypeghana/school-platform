<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // ── Challenge (post-login code entry) ─────────────────────────────────────

    /**
     * Show the TOTP challenge form.
     * Reached only when session has auth.2fa_pending_user_id.
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.2fa_pending_user_id')) {
            // Redirect to the correct portal login based on stored role, or default to admin
            return redirect()->route($this->loginRoute($request->session()->get('auth.2fa_pending_role')));
        }

        return view('auth.two-factor.challenge');
    }

    /**
     * Verify TOTP code and complete login.
     */
    public function challenge(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        $userId = $request->session()->get('auth.2fa_pending_user_id');
        $role   = $request->session()->get('auth.2fa_pending_role');

        if (! $userId) {
            return redirect()->route($this->loginRoute($role));
        }

        $user = \App\Models\User::find($userId);

        if (! $user || ! $user->two_factor_secret) {
            $request->session()->forget(['auth.2fa_pending_user_id', 'auth.2fa_pending_role']);
            return redirect()->route('admin.login');
        }

        $code = str_replace(' ', '', $request->input('code'));

        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $code,
            2  // window: ±2 × 30s = ±1 minute tolerance
        );

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid or expired code. Please try again.']);
        }

        // Complete the login
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['auth.2fa_pending_user_id', 'auth.2fa_pending_role']);
        $request->session()->put('auth.2fa_verified', true);

        if ($role === 'super_admin') {
            return redirect()->route('superadmin.dashboard');
        }

        return redirect()->route('admin.dashboard');
    }

    // ── Setup (enable 2FA from account settings) ──────────────────────────────

    /**
     * Show QR code setup page. Generates a new secret if not yet enabled.
     */
    public function showSetup(Request $request): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Generate a fresh secret for new setup (don't persist until confirmed)
        if (! $request->session()->has('2fa_setup_secret')) {
            $secret = $this->google2fa->generateSecretKey();
            $request->session()->put('2fa_setup_secret', $secret);
        }

        $secret = $request->session()->get('2fa_setup_secret');

        $qrUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'SchoolMS'),
            $user->email,
            $secret
        );

        return view('auth.two-factor.setup', compact('secret', 'qrUrl'));
    }

    /**
     * Confirm the TOTP code and enable 2FA for the account.
     */
    public function confirmSetup(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        /** @var \App\Models\User $user */
        $user   = Auth::user();
        $secret = $request->session()->get('2fa_setup_secret');

        if (! $secret) {
            return redirect()->route($this->setupRoute())
                ->withErrors(['code' => 'Setup session expired. Please start again.']);
        }

        $code  = str_replace(' ', '', $request->input('code'));
        $valid = $this->google2fa->verifyKey($secret, $code, 2);

        if (! $valid) {
            return back()->withErrors(['code' => 'Code does not match. Please try again.']);
        }

        $user->update([
            'two_factor_secret'  => $secret,
            'two_factor_enabled' => true,
        ]);

        $request->session()->forget('2fa_setup_secret');
        $request->session()->put('auth.2fa_verified', true);

        return redirect()->route($this->settingsRoute())
            ->with('success', 'Two-factor authentication has been enabled.');
    }

    /**
     * Disable 2FA (requires password confirmation).
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string|current_password',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->update([
            'two_factor_secret'  => null,
            'two_factor_enabled' => false,
        ]);

        $request->session()->forget('auth.2fa_verified');

        return redirect()->route($this->settingsRoute())
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Return the correct login route for a role (used as fallback when session is stale). */
    private function loginRoute(?string $role): string
    {
        return $role === 'super_admin' ? 'superadmin.login' : 'admin.login';
    }

    /** Return the correct 2FA setup route name for the current user's role. */
    private function setupRoute(): string
    {
        return Auth::user()?->role === 'super_admin'
            ? 'superadmin.2fa.setup'
            : 'admin.2fa.setup';
    }

    /** Return the correct account settings route name for the current user's role. */
    private function settingsRoute(): string
    {
        return Auth::user()?->role === 'super_admin'
            ? 'superadmin.dashboard'
            : 'admin.settings.account';
    }
}
