<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSchoolProfileRequest;
use App\Models\AcademicTerm;
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

    // ── Website Content ───────────────────────────────────────────────────────

    public function websiteContent(): View
    {
        $tenant = app('currentTenant');
        return view('admin.settings.website', compact('tenant'));
    }

    public function updateWebsiteContent(Request $request): RedirectResponse
    {
        $tenant = app('currentTenant');

        $data = $request->validate([
            'hero_tagline'      => 'nullable|string|max:150',
            'hero_subtitle'     => 'nullable|string|max:300',
            'established_year'  => 'nullable|string|max:10',
            'stats_students'    => 'nullable|string|max:20',
            'stats_staff'       => 'nullable|string|max:20',
            'stats_classes'     => 'nullable|string|max:20',
            'stats_bece_rate'   => 'nullable|string|max:10',
            'about_intro'       => 'nullable|string|max:2000',
            'mission'           => 'nullable|string|max:1000',
            'vision'            => 'nullable|string|max:1000',
            'admissions_open'   => 'nullable|boolean',
            'admissions_note'   => 'nullable|string|max:500',
            'footer_tagline'    => 'nullable|string|max:200',
        ]);

        // Merge new values with existing so un-submitted fields aren't lost
        $existing = $tenant->website_content ?? [];
        $merged   = array_merge($existing, array_filter($data, fn ($v) => $v !== null));
        $merged['admissions_open'] = $request->boolean('admissions_open');

        // Handle gallery image uploads
        $gallery = $existing['gallery_images'] ?? [];
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $file) {
                $path    = $file->store('gallery', 'public');
                $gallery[] = $path;
            }
        }

        // Handle gallery image removals
        $remove = $request->input('remove_gallery', []);
        if (!empty($remove)) {
            foreach ($remove as $path) {
                Storage::disk('public')->delete($path);
            }
            $gallery = array_values(array_filter($gallery, fn ($p) => !in_array($p, $remove)));
        }

        $merged['gallery_images'] = $gallery;

        $tenant->update(['website_content' => $merged]);

        return back()->with('success', 'Website content updated successfully.');
    }

    // ── Academic Calendar ─────────────────────────────────────────────────────

    public function calendar(): View
    {
        $tenant = app('currentTenant');

        // All terms grouped by academic year, newest year first
        $years = \App\Models\AcademicYear::with(['terms' => fn ($q) => $q->orderBy('term_number')])
            ->latest('id')
            ->get();

        $currentTermId = $tenant->current_term_id;

        return view('admin.settings.calendar', compact('tenant', 'years', 'currentTermId'));
    }

    public function updateCalendar(Request $request): RedirectResponse
    {
        $tenant = app('currentTenant');

        $data = $request->validate([
            'current_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
        ]);

        $tenant->update(['current_term_id' => $data['current_term_id'] ?: null]);

        if ($data['current_term_id']) {
            $term = AcademicTerm::with('academicYear')->find($data['current_term_id']);
            $label = $term ? "{$term->term_name} — {$term->academicYear?->year_label}" : 'selected term';
            return back()->with('success', "Active term set to {$label}.");
        }

        return back()->with('success', 'Calendar reset — will follow the global active term.');
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
