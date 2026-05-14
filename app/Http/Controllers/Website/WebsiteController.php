<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Mail\AdmissionReceivedMail;
use App\Models\Admission;
use App\Models\AcademicTerm;
use App\Notifications\NewAdmissionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function home(): View
    {
        return view('website.home');
    }

    public function about(): View
    {
        return view('website.about');
    }

    public function academics(): View
    {
        return view('website.academics');
    }

    public function news(): View
    {
        return view('website.news');
    }

    public function gallery(): View
    {
        return view('website.gallery');
    }

    public function contact(): View
    {
        return view('website.contact');
    }

    public function admissions(): View
    {
        $term = AcademicTerm::current();
        return view('website.admissions', compact('term'));
    }

    /**
     * Submit an online admission application.
     */
    public function applyAdmission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'date_of_birth'   => 'required|date|before:today',
            'gender'          => 'required|in:male,female',
            'desired_class'   => 'required|string|max:150',
            'guardian_name'   => 'required|string|max:150',
            'guardian_phone'  => 'required|string|max:20',
            'guardian_email'  => 'nullable|email|max:150',
            'address'         => 'nullable|string|max:255',
            'message'         => 'nullable|string|max:1000',
        ]);

        $tenant = app('currentTenant');
        $term   = AcademicTerm::current();

        $admission = Admission::create([
            'tenant_id'          => $tenant?->id,
            'term_id'            => $term?->id,
            'first_name'         => $data['first_name'],
            'last_name'          => $data['last_name'],
            'date_of_birth'      => $data['date_of_birth'],
            'gender'             => $data['gender'],
            'class_applying_for' => $data['desired_class'],
            'guardian_name'      => $data['guardian_name'],
            'guardian_phone'     => $data['guardian_phone'],
            'guardian_email'     => $data['guardian_email'] ?? null,
            'address'            => $data['address'] ?? null,
            'notes'              => $data['message'] ?? null,
            'status'             => 'pending',
            'submitted_at'       => now(),
        ]);

        // 1. Notify the school admin via in-app notification
        if ($tenant) {
            try {
                $tenant->notify(new NewAdmissionNotification($admission));
            } catch (\Throwable $e) {
                Log::error('NewAdmissionNotification failed', [
                    'tenant_id' => $tenant->id, 'admission_id' => $admission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Email confirmation to guardian (queued — non-blocking)
        if ($admission->guardian_email) {
            try {
                Mail::to($admission->guardian_email)
                    ->queue(new AdmissionReceivedMail($admission, isApplicantCopy: true));
            } catch (\Throwable $e) {
                Log::error('AdmissionReceivedMail failed', ['error' => $e->getMessage()]);
            }
        }

        // 3. Email notification to school admin email
        if ($tenant?->email) {
            try {
                Mail::to($tenant->email)
                    ->queue(new AdmissionReceivedMail($admission, isApplicantCopy: false));
            } catch (\Throwable $e) {
                Log::error('AdmissionReceivedMail (admin copy) failed', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('applied', true);
    }
}
