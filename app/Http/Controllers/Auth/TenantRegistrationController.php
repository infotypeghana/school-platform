<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SchoolApprovedNotification;
use App\Notifications\SchoolRegistrationVerifyEmail;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantRegistrationController extends Controller
{
    /** How long the pending-verification cache entry lives (minutes). */
    private const VERIFY_TTL = 60;

    // ── Show registration form ────────────────────────────────────────────────

    public function showForm(): View
    {
        return view('auth.register-school');
    }

    // ── Handle form submission ────────────────────────────────────────────────

    /**
     * Phase 1 — Validate and send verification email.
     *
     * The tenant record is NOT created yet.  The validated data is stored in
     * the cache against a random token and a verification link is emailed to
     * contact_email.  This prevents junk registrations with fake addresses.
     */
    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name'        => ['required', 'string', 'max:120'],
            'contact_name'       => ['required', 'string', 'max:120'],
            'contact_email'      => ['required', 'email', 'max:150', 'unique:tenants,contact_email'],
            'contact_phone'      => ['required', 'string', 'max:20'],
            'school_type'        => ['required', 'in:public,private,international'],
            'district'           => ['required', 'string', 'max:100'],
            'estimated_students' => ['required', 'integer', 'min:10', 'max:9999'],
            'address'            => ['nullable', 'string', 'max:255'],
        ]);

        // Store validated data in cache keyed by a one-time token
        $token = Str::random(64);
        Cache::put("school_reg:{$token}", $data, now()->addMinutes(self::VERIFY_TTL));

        // Build the verification URL and send the email via an on-demand notification
        // (no User model required — the recipient is just an email address at this stage)
        $verifyUrl = route('register.school.verify', ['token' => $token]);

        Notification::route('mail', $data['contact_email'])
            ->notify(new SchoolRegistrationVerifyEmail(
                $data['contact_name'],
                $data['school_name'],
                $verifyUrl,
            ));

        return redirect()->route('register.school.check-email');
    }

    // ── Check-email page (shown after form submit) ─────────────────────────────

    public function checkEmail(): View
    {
        return view('auth.register-school-check-email');
    }

    // ── Phase 2 — Verify email and create pending tenant ──────────────────────

    /**
     * GET /register/school/verify/{token}
     *
     * Retrieves the cached registration data, creates the pending Tenant, and
     * notifies super admins — only once the applicant has clicked their link.
     */
    public function verify(string $token): RedirectResponse|View
    {
        $data = Cache::pull("school_reg:{$token}");

        if (! $data) {
            // Token expired or already used
            return view('auth.register-school-verify-expired');
        }

        // Guard against duplicate submissions (e.g. link clicked twice)
        if (Tenant::where('contact_email', $data['contact_email'])->exists()) {
            return redirect()->route('register.school.done');
        }

        $slug   = Str::slug($data['school_name']) . '-' . Str::random(4);
        $regToken = Str::random(64);

        $tenant = Tenant::create([
            'name'               => $data['school_name'],
            'slug'               => $slug,
            'uuid'               => Str::uuid(),
            'contact_name'       => $data['contact_name'],
            'contact_email'      => $data['contact_email'],
            'contact_phone'      => $data['contact_phone'],
            'school_type'        => $data['school_type'],
            'district'           => $data['district'],
            'estimated_students' => $data['estimated_students'],
            'address'            => $data['address'] ?? null,
            'email'              => $data['contact_email'],
            'phone'              => $data['contact_phone'],
            'status'             => 'pending',
            'registered_at'      => now(),
            'registration_token' => $regToken,
        ]);

        // Notify all super admins about the verified registration
        $superAdmins = User::where('role', 'super_admin')->get();
        Notification::send($superAdmins, new \App\Notifications\NewSchoolRegistrationNotification($tenant));

        return redirect()->route('register.school.done');
    }

    // ── Thank-you page ────────────────────────────────────────────────────────

    public function done(): View
    {
        return view('auth.register-school-done');
    }

    // ── Super admin: approve a pending registration ───────────────────────────

    public function approve(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_if($tenant->status !== 'pending', 422, 'This school is not in pending status.');

        $data = $request->validate([
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        // Create school admin account
        $admin = User::create([
            'name'      => $tenant->contact_name ?? $tenant->name . ' Admin',
            'email'     => $tenant->contact_email,
            'password'  => Hash::make($data['admin_password']),
            'role'      => 'school_admin',
            'tenant_id' => $tenant->id,
        ]);

        // Update tenant to trial
        $tenant->update([
            'status'             => 'trial',
            'approved_at'        => now(),
            'approved_by'        => auth()->id(),
            'registration_token' => null,
        ]);

        // Create trial subscription
        app(SubscriptionService::class)->createTrialSubscription($tenant);

        // Notify the school admin with login credentials
        $admin->notify(new SchoolApprovedNotification($tenant, $data['admin_password']));

        return redirect()->route('superadmin.tenants.show', $tenant)
            ->with('success', $tenant->name . ' approved. Login credentials sent to ' . $tenant->contact_email . '.');
    }
}
