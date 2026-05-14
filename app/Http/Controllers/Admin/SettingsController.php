<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSchoolProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    // ── School Profile ────────────────────────────────────────────────────────

    public function school(): View
    {
        $tenant = app('currentTenant');
        return view('admin.settings.school', compact('tenant'));
    }

    public function updateSchool(UpdateSchoolProfileRequest $request): RedirectResponse
    {
        $tenant = app('currentTenant');

        $data = $request->safe()->only(['name', 'address', 'phone', 'contact_phone', 'contact_email', 'primary_color']);

        // Handle logo removal
        if ($request->boolean('remove_logo') && $tenant->logo) {
            Storage::disk('public')->delete($tenant->logo);
            $data['logo'] = null;
        }

        // Handle logo upload (takes precedence over remove)
        if ($request->hasFile('logo')) {
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $tenant->update($data);

        return back()->with('success', 'School profile updated successfully.');
    }

    // ── Account Settings ──────────────────────────────────────────────────────

    public function account(): View
    {
        return view('admin.settings.account');
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password'      => ['required', 'current_password'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        auth()->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
