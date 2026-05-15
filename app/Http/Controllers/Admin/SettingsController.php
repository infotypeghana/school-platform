<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSchoolProfileRequest;
use App\Services\GradeCalculator;
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
        $tenant       = app('currentTenant');
        $defaultScale = GradeCalculator::scale();
        return view('admin.settings.school', compact('tenant', 'defaultScale'));
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

    // ── Grading Settings ──────────────────────────────────────────────────────

    public function grading(): View
    {
        $tenant       = app('currentTenant');
        $defaultScale = GradeCalculator::scale();
        $settings     = GradeCalculator::tenantSettings($tenant);
        return view('admin.settings.grading', compact('tenant', 'defaultScale', 'settings'));
    }

    public function updateGrading(Request $request): RedirectResponse
    {
        $tenant = app('currentTenant');

        $data = $request->validate([
            'ca_max'              => 'required|integer|min:1|max:99',
            'exam_max'            => 'required|integer|min:1|max:99',
            'scale'               => 'required|array|min:1',
            'scale.*.min'         => 'required|integer|min:0|max:100',
            'scale.*.max'         => 'required|integer|min:0|max:100',
            'scale.*.grade'       => 'required|string|max:10',
            'scale.*.remark'      => 'required|string|max:50',
            'scale.*.points'      => 'required|integer|min:1|max:20',
        ]);

        // Ensure ca_max + exam_max = 100
        if ((int)$data['ca_max'] + (int)$data['exam_max'] !== 100) {
            return back()->withInput()
                ->withErrors(['ca_max' => 'CA max and Exam max must add up to exactly 100.']);
        }

        // Sort scale by min descending so the highest grade is first (A1 at top)
        $scale = collect($data['scale'])
            ->map(fn ($b) => [
                'min'    => (int) $b['min'],
                'max'    => (int) $b['max'],
                'grade'  => trim($b['grade']),
                'remark' => trim($b['remark']),
                'points' => (int) $b['points'],
            ])
            ->sortByDesc('min')
            ->values()
            ->toArray();

        $tenant->update([
            'grading_settings' => [
                'ca_max'   => (int) $data['ca_max'],
                'exam_max' => (int) $data['exam_max'],
                'scale'    => $scale,
            ],
        ]);

        return back()->with('success', 'Grading settings updated. New scores will use the updated scale immediately.');
    }

    public function resetGrading(): RedirectResponse
    {
        $tenant = app('currentTenant');
        $tenant->update(['grading_settings' => null]);
        return back()->with('success', 'Grading settings reset to the GES standard scale.');
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
